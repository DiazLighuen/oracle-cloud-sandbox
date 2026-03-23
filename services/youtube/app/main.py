import httpx
from fastapi import Depends, FastAPI, Header, HTTPException, Query, Request
from fastapi.responses import StreamingResponse
from starlette.background import BackgroundTask

from app import cache, cookies, quota
from app.auth import verify_jwt
from app.clients import invidious, youtube as yt_client

app = FastAPI(title="YouTube Service", docs_url=None, redoc_url=None)

_SUBSCRIPTIONS_TTL = 3600   # 1h
_CHANNEL_IDS_TTL   = 3600   # 1h — subscription list changes rarely
_HOME_TTL          = 900    # 15min
_LIVE_TTL          = 300    # 5min — live status changes fast
_SEARCH_TTL        = 300    # 5min
_VIDEO_META_TTL    = 3600   # 1h


def _google_token(x_google_token: str = Header(..., alias="X-Google-Token")) -> str:
    if not x_google_token:
        raise HTTPException(status_code=422, detail="X-Google-Token header is required")
    return x_google_token


# ── GET /subscriptions ────────────────────────────────────────────────────────

@app.get("/subscriptions")
async def subscriptions(
    page_token: str | None = Query(None),
    _jwt: dict = Depends(verify_jwt),
    google_token: str = Depends(_google_token),
):
    cache_key = f"subs:{google_token[:16]}:{page_token}"
    cached = cache.get(cache_key, _SUBSCRIPTIONS_TTL)
    if cached is not None:
        return cached

    result = yt_client.get_subscriptions(google_token, page_token)
    cache.set(cache_key, result)
    return result


# ── GET /home ─────────────────────────────────────────────────────────────────

@app.get("/home")
async def home(
    page: int = Query(1, ge=1),
    _jwt: dict = Depends(verify_jwt),
    google_token: str = Depends(_google_token),
):
    cache_key = f"home:{google_token[:16]}:p{page}"
    cached = cache.get(cache_key, _HOME_TTL)
    if cached is not None:
        return cached

    # Channel IDs are cached separately (1h) so a home feed cache miss
    # doesn't re-fetch subscriptions from YouTube API every 15 min.
    ch_key    = f"channel_ids:{google_token[:16]}"
    ch_cached = cache.get(ch_key, _CHANNEL_IDS_TTL)
    result = await yt_client.get_home_feed(google_token, page, cached_channel_ids=ch_cached)
    if result.get("_channel_ids"):
        cache.set(ch_key, result.pop("_channel_ids"))
    cache.set(cache_key, result)
    return result


# ── GET /live ─────────────────────────────────────────────────────────────────

@app.get("/live")
async def live(
    _jwt: dict = Depends(verify_jwt),
    google_token: str = Depends(_google_token),
):
    cache_key = f"live:{google_token[:16]}"
    cached = cache.get(cache_key, _LIVE_TTL)
    if cached is not None:
        return cached

    ch_key    = f"channel_ids:{google_token[:16]}"
    ch_cached = cache.get(ch_key, _CHANNEL_IDS_TTL)
    result = await yt_client.get_live_streams(google_token, cached_channel_ids=ch_cached)
    if result.get("_channel_ids"):
        cache.set(ch_key, result.pop("_channel_ids"))
    cache.set(cache_key, result)
    return result


# ── GET /search ───────────────────────────────────────────────────────────────

@app.get("/search")
async def search(
    q: str = Query(..., min_length=1),
    type: str = Query("video"),
    page_token: str | None = Query(None),
    _jwt: dict = Depends(verify_jwt),
    google_token: str = Depends(_google_token),
):
    cache_key = f"search:{google_token[:16]}:{q}:{type}:{page_token}"
    cached = cache.get(cache_key, _SEARCH_TTL)
    if cached is not None:
        return cached

    result = yt_client.search(google_token, q, type, page_token)
    cache.set(cache_key, result)
    return result


# ── GET /video/{id} ───────────────────────────────────────────────────────────

@app.get("/video/{video_id}")
async def video_detail(
    video_id: str,
    _jwt: dict = Depends(verify_jwt),
    google_token: str = Depends(_google_token),
):
    # Metadata: cached
    meta_key = f"meta:{video_id}"
    metadata = cache.get(meta_key, _VIDEO_META_TTL)
    if metadata is None:
        metadata = yt_client.get_video_metadata(google_token, video_id)
        cache.set(meta_key, metadata)

    # Streams: never cached — URLs expire quickly
    streams = await invidious.get_streams(video_id)
    return {**metadata, **streams}


# ── GET /stream/{video_id} ────────────────────────────────────────────────────
# Proxy the video stream through the backend so the client never receives a
# YouTube CDN URL tied to the server's IP. Supports HTTP Range requests for
# seeking (AVPlayer, <video>). Stream URLs expire ~6h so they are cached 30min.

_STREAM_URL_TTL = 1800  # 30 min — URLs typically valid ~6h, renew early

@app.get("/stream/{video_id}")
async def stream_video(
    video_id: str,
    request: Request,
    quality: str | None = Query(None, description="Desired quality label, e.g. '720p'. Falls back to first available."),
    _jwt: dict = Depends(verify_jwt),
):
    # Resolve the stream URL (cached to avoid calling yt-dlp on every byte-range chunk)
    url_cache_key = f"stream_url:{video_id}:{quality or 'best'}"
    stream_url = cache.get(url_cache_key, _STREAM_URL_TTL)

    if stream_url is None:
        streams = await invidious.get_streams(video_id)
        candidates = streams.get("format_streams", [])

        # Fallback to best video-only stream if no muxed streams available.
        # YouTube is phasing out muxed streams; video-only is a last resort
        # (no audio) until a proper mux solution is in place.
        if not candidates:
            candidates = [
                {"url": s["url"], "quality": s.get("quality", ""), "ext": s.get("ext", "")}
                for s in streams.get("video_streams", [])
            ]
        if not candidates:
            audio_count = len(streams.get("audio_streams", []))
            raise HTTPException(
                status_code=404,
                detail=f"No playable stream found (audio_only={audio_count})",
            )

        if quality:
            stream_url = next(
                (s["url"] for s in candidates if quality in s.get("quality", "")),
                candidates[0]["url"],
            )
        else:
            stream_url = candidates[0]["url"]
        cache.set(url_cache_key, stream_url)

    # Forward Range header so seeking works on the client
    upstream_headers = {}
    if range_header := request.headers.get("Range"):
        upstream_headers["Range"] = range_header

    client = httpx.AsyncClient(timeout=30)
    upstream_req = client.build_request("GET", stream_url, headers=upstream_headers)
    upstream_resp = await client.send(upstream_req, stream=True)

    # Pass relevant headers back to the client
    forward = ["content-type", "content-length", "content-range", "accept-ranges"]
    resp_headers = {k: v for k, v in upstream_resp.headers.items() if k.lower() in forward}
    resp_headers.setdefault("accept-ranges", "bytes")

    return StreamingResponse(
        upstream_resp.aiter_bytes(chunk_size=65536),
        status_code=upstream_resp.status_code,
        headers=resp_headers,
        background=BackgroundTask(client.aclose),
    )


# ── GET /quota ────────────────────────────────────────────────────────────────

@app.get("/quota")
async def quota_status():
    return quota.status()


# ── GET /health ───────────────────────────────────────────────────────────────

@app.get("/health")
async def health():
    return {"status": "ok", "cookies": cookies.status()}
