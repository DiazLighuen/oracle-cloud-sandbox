"""YouTube Data API v3 wrapper.

Uses the caller's Google access token (X-Google-Token header) so the backend
never stores refresh tokens. The token is passed via `google.oauth2.credentials`.
"""
import asyncio
import re

import httpx
from fastapi import HTTPException
from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

from app import quota as _quota

_ITEMS_PER_PAGE = 20
_VIDEOS_PER_CHANNEL = 8   # videos fetched per channel for home feed
_MAX_CHANNELS = 500        # safety cap
_CONCURRENCY = 20          # max parallel playlist fetches


def _build_service(access_token: str):
    creds = Credentials(token=access_token)
    return build("youtube", "v3", credentials=creds, cache_discovery=False)


def _parse_duration_seconds(iso: str) -> int:
    """Parse ISO 8601 duration like PT1M30S → seconds."""
    match = re.match(r"PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?", iso)
    if not match:
        return 0
    h, m, s = (int(g or 0) for g in match.groups())
    return h * 3600 + m * 60 + s


def _is_short(duration_iso: str) -> bool:
    return _parse_duration_seconds(duration_iso) < 60



def _channel_item(item: dict) -> dict:
    snippet = item.get("snippet", {})
    stats = item.get("statistics", {})
    return {
        "channel_id": item["id"],
        "name": snippet.get("title", ""),
        "avatar": (snippet.get("thumbnails") or {}).get("default", {}).get("url"),
        "subscriber_count": int(stats["subscriberCount"]) if "subscriberCount" in stats else None,
    }


def _video_item(video: dict) -> dict:
    snippet = video.get("snippet", {})
    stats = video.get("statistics", {})
    content = video.get("contentDetails", {})
    thumbs = snippet.get("thumbnails", {})
    thumb = (
        thumbs.get("maxres")
        or thumbs.get("high")
        or thumbs.get("medium")
        or thumbs.get("default")
        or {}
    )
    return {
        "id": video["id"],
        "title": snippet.get("title", ""),
        "channel_id": snippet.get("channelId", ""),
        "channel_name": snippet.get("channelTitle", ""),
        "thumbnail": thumb.get("url"),
        "duration": content.get("duration", "PT0S"),
        "published_at": snippet.get("publishedAt", ""),
        "view_count": int(stats["viewCount"]) if "viewCount" in stats else None,
    }


# ── Subscriptions ─────────────────────────────────────────────────────────────

def get_subscriptions(access_token: str, page_token: str | None = None) -> dict:
    try:
        yt = _build_service(access_token)
        params = {
            "part": "snippet",
            "mine": True,
            "maxResults": _ITEMS_PER_PAGE,
            "order": "alphabetical",
        }
        if page_token:
            params["pageToken"] = page_token

        # subscriptions.list returns channelId; we need channel details
        sub_resp = yt.subscriptions().list(**params).execute()
        _quota.add(_quota.COST_LIST)
        channel_ids = [
            item["snippet"]["resourceId"]["channelId"]
            for item in sub_resp.get("items", [])
        ]

        channels = []
        if channel_ids:
            ch_resp = (
                yt.channels()
                .list(
                    part="snippet,statistics",
                    id=",".join(channel_ids),
                    maxResults=len(channel_ids),
                )
                .execute()
            )
            _quota.add(_quota.COST_LIST)
            channels = [_channel_item(c) for c in ch_resp.get("items", [])]

        return {
            "items": channels,
            "next_page_token": sub_resp.get("nextPageToken"),
            "has_more": "nextPageToken" in sub_resp,
        }
    except HttpError as e:
        _handle_yt_error(e)


# ── Home feed ─────────────────────────────────────────────────────────────────

async def get_home_feed(
    access_token: str,
    page: int = 1,
    cached_channel_ids: list[str] | None = None,
) -> dict:
    """
    Builds a chronological feed of recent videos from the user's subscriptions.
    Strategy:
      1. Fetch all subscribed channel IDs (paginated, cached upstream).
      2. Fetch last _VIDEOS_PER_CHANNEL uploads per channel concurrently via
         the REST API with httpx (avoids googleapiclient's sync executor).
      3. Merge, deduplicate, sort by publishedAt desc.
      4. Paginate.
      5. Enrich the current page with videos.list (duration + stats).
      6. Filter Shorts (<60 s).
    """
    yt = _build_service(access_token)

    # 1. Collect all subscribed channel IDs (use cache if available)
    fresh_channel_ids: list[str] | None = None
    if cached_channel_ids is not None:
        channel_ids = cached_channel_ids
    else:
        channel_ids = []
        next_token: str | None = None
        while len(channel_ids) < _MAX_CHANNELS:
            try:
                params: dict = {"part": "snippet", "mine": True, "maxResults": 50}
                if next_token:
                    params["pageToken"] = next_token
                resp = yt.subscriptions().list(**params).execute()
            except HttpError as e:
                _handle_yt_error(e)
            _quota.add(_quota.COST_LIST)
            channel_ids.extend(
                item["snippet"]["resourceId"]["channelId"]
                for item in resp.get("items", [])
            )
            next_token = resp.get("nextPageToken")
            if not next_token:
                break
        fresh_channel_ids = channel_ids

    if not channel_ids:
        return {"items": [], "page": page, "has_more": False}

    # 2. Fetch recent uploads per channel concurrently
    # Uploads playlist ID = channel ID with "UC" → "UU"
    sem = asyncio.Semaphore(_CONCURRENCY)
    headers = {"Authorization": f"Bearer {access_token}"}

    async def fetch_channel(client: httpx.AsyncClient, channel_id: str) -> list[dict]:
        playlist_id = "UU" + channel_id[2:]
        async with sem:
            try:
                r = await client.get(
                    "https://www.googleapis.com/youtube/v3/playlistItems",
                    params={
                        "part": "snippet",
                        "playlistId": playlist_id,
                        "maxResults": _VIDEOS_PER_CHANNEL,
                    },
                    headers=headers,
                )
            except Exception:
                return []
        if r.status_code != 200:
            return []
        result = []
        for item in r.json().get("items", []):
            s = item.get("snippet", {})
            vid_id = s.get("resourceId", {}).get("videoId")
            if not vid_id:
                continue
            thumbs = s.get("thumbnails") or {}
            thumb = (thumbs.get("high") or thumbs.get("medium") or thumbs.get("default") or {})
            result.append({
                "id": vid_id,
                "title": s.get("title", ""),
                "channel_id": s.get("channelId", channel_id),
                "channel_name": s.get("channelTitle", ""),
                "published_at": s.get("publishedAt", ""),
                "thumbnail": thumb.get("url"),
            })
        return result

    async with httpx.AsyncClient(timeout=15.0) as client:
        batches = await asyncio.gather(
            *[fetch_channel(client, cid) for cid in channel_ids]
        )
    _quota.add(len(channel_ids) * _quota.COST_LIST)  # 1 unit per playlistItems.list call

    # 3. Merge, deduplicate, sort
    seen: set[str] = set()
    all_videos: list[dict] = []
    for batch in batches:
        for v in batch:
            if v["id"] not in seen:
                seen.add(v["id"])
                all_videos.append(v)
    all_videos.sort(key=lambda x: x["published_at"], reverse=True)

    # 4. Paginate
    start = (page - 1) * _ITEMS_PER_PAGE
    page_items = all_videos[start : start + _ITEMS_PER_PAGE]
    has_more = len(all_videos) > start + _ITEMS_PER_PAGE

    if not page_items:
        return {"items": [], "page": page, "has_more": False}

    # 5. Fetch Shorts IDs only for channels that appear on this page (UUSH playlist)
    page_channel_ids = list({v["channel_id"] for v in page_items})

    async def fetch_shorts_ids(client: httpx.AsyncClient, channel_id: str) -> set[str]:
        playlist_id = "UUSH" + channel_id[2:]
        async with sem:
            try:
                r = await client.get(
                    "https://www.googleapis.com/youtube/v3/playlistItems",
                    params={"part": "snippet", "playlistId": playlist_id, "maxResults": 50},
                    headers=headers,
                )
            except Exception:
                return set()
        if r.status_code != 200:
            return set()
        return {
            item["snippet"]["resourceId"]["videoId"]
            for item in r.json().get("items", [])
            if item.get("snippet", {}).get("resourceId", {}).get("videoId")
        }

    async with httpx.AsyncClient(timeout=15.0) as client:
        shorts_sets = await asyncio.gather(
            *[fetch_shorts_ids(client, cid) for cid in page_channel_ids]
        )
    _quota.add(len(page_channel_ids) * _quota.COST_LIST)  # 1 unit per UUSH playlistItems.list
    shorts_ids: set[str] = set().union(*shorts_sets)

    # 6. Enrich with duration + stats
    try:
        vids_resp = yt.videos().list(
            part="contentDetails,statistics",
            id=",".join(v["id"] for v in page_items),
        ).execute()
        _quota.add(_quota.COST_LIST)
        details = {v["id"]: v for v in vids_resp.get("items", [])}
    except HttpError as e:
        _handle_yt_error(e)

    # 7. Filter Shorts — UUSH playlist match is authoritative; duration < 60s as fallback
    result: list[dict] = []
    for raw in page_items:
        if raw["id"] in shorts_ids:
            continue
        detail = details.get(raw["id"])
        if not detail:
            continue
        duration = detail.get("contentDetails", {}).get("duration", "PT0S")
        if _is_short(duration):
            continue
        stats = detail.get("statistics", {})
        raw["duration"] = duration
        raw["view_count"] = int(stats["viewCount"]) if "viewCount" in stats else None
        result.append(raw)

    out: dict = {"items": result, "page": page, "has_more": has_more}
    if fresh_channel_ids is not None:
        out["_channel_ids"] = fresh_channel_ids
    return out


# ── Live streams ──────────────────────────────────────────────────────────────

async def get_live_streams(
    access_token: str,
    cached_channel_ids: list[str] | None = None,
) -> dict:
    """
    Returns live streams from subscribed channels, sorted by viewer count.

    Architecture: each item includes `platform: "youtube"` so the live tab
    can merge results from other platforms (e.g. Twitch) without schema changes.

    Quota: ~100 units (search.list) + 1 unit (videos.list) per cache miss.
    Cache TTL: 5 min (controlled in main.py).
    """
    yt = _build_service(access_token)

    # 1. Collect all subscribed channel IDs (use cache if available)
    fresh_channel_ids: list[str] | None = None
    if cached_channel_ids is not None:
        channel_ids: set[str] = set(cached_channel_ids)
    else:
        channel_ids = set()
        next_token: str | None = None
        for _ in range(10):  # max 500 channels
            try:
                params: dict = {"part": "snippet", "mine": True, "maxResults": 50}
                if next_token:
                    params["pageToken"] = next_token
                resp = yt.subscriptions().list(**params).execute()
            except HttpError as e:
                _handle_yt_error(e)
            _quota.add(_quota.COST_LIST)
            for item in resp.get("items", []):
                channel_ids.add(item["snippet"]["resourceId"]["channelId"])
            next_token = resp.get("nextPageToken")
            if not next_token:
                break
        fresh_channel_ids = list(channel_ids)

    if not channel_ids:
        return {"items": []}

    # 2. Search for live streams globally — filter by subscriptions client-side.
    #    search.list costs 100 quota units; cached 5 min upstream.
    try:
        search_resp = yt.search().list(
            part="snippet",
            eventType="live",
            type="video",
            maxResults=50,
            order="viewCount",
        ).execute()
        _quota.add(_quota.COST_SEARCH)
    except HttpError as e:
        _handle_yt_error(e)

    live_items = [
        item for item in search_resp.get("items", [])
        if item.get("snippet", {}).get("channelId") in channel_ids
    ]

    if not live_items:
        return {"items": []}

    # 3. Enrich with concurrent viewer count and actual start time
    video_ids = [item["id"]["videoId"] for item in live_items]
    try:
        vids_resp = yt.videos().list(
            part="statistics,liveStreamingDetails",
            id=",".join(video_ids),
        ).execute()
        _quota.add(_quota.COST_LIST)
        details = {v["id"]: v for v in vids_resp.get("items", [])}
    except HttpError as e:
        _handle_yt_error(e)

    result = []
    for item in live_items:
        vid_id  = item["id"]["videoId"]
        snippet = item.get("snippet", {})
        detail  = details.get(vid_id, {})
        live_det = detail.get("liveStreamingDetails", {})
        thumbs  = snippet.get("thumbnails", {})
        thumb   = (thumbs.get("high") or thumbs.get("medium") or thumbs.get("default") or {})
        viewers = live_det.get("concurrentViewers")
        result.append({
            "id":           vid_id,
            "platform":     "youtube",   # extensibility hook for future platforms
            "title":        snippet.get("title", ""),
            "channel_id":   snippet.get("channelId", ""),
            "channel_name": snippet.get("channelTitle", ""),
            "thumbnail":    thumb.get("url"),
            "viewer_count": int(viewers) if viewers else None,
            "started_at":   live_det.get("actualStartTime"),
        })

    result.sort(key=lambda x: x["viewer_count"] or 0, reverse=True)
    out: dict = {"items": result}
    if fresh_channel_ids is not None:
        out["_channel_ids"] = fresh_channel_ids
    return out


# ── Search ────────────────────────────────────────────────────────────────────

def search(
    access_token: str,
    q: str,
    type: str = "video",
    page_token: str | None = None,
) -> dict:
    if type not in ("video", "channel"):
        raise HTTPException(status_code=422, detail="type must be 'video' or 'channel'")
    try:
        yt = _build_service(access_token)
        resp = yt.search().list(
            part="snippet",
            q=q,
            type=type,
            maxResults=_ITEMS_PER_PAGE,
            **({"pageToken": page_token} if page_token else {}),
        ).execute()
        _quota.add(_quota.COST_SEARCH)
    except HttpError as e:
        _handle_yt_error(e)

    items = resp.get("items", [])

    if type == "channel":
        channel_ids = [
            item["id"]["channelId"] for item in items if item.get("id", {}).get("channelId")
        ]
        channels = []
        if channel_ids:
            try:
                ch_resp = yt.channels().list(
                    part="snippet,statistics",
                    id=",".join(channel_ids),
                    maxResults=len(channel_ids),
                ).execute()
                _quota.add(_quota.COST_LIST)
                channels = [_channel_item(c) for c in ch_resp.get("items", [])]
            except HttpError as e:
                _handle_yt_error(e)
        return {
            "items": channels,
            "next_page_token": resp.get("nextPageToken"),
            "has_more": "nextPageToken" in resp,
        }

    # type == "video"
    video_ids = [
        item["id"]["videoId"] for item in items if item.get("id", {}).get("videoId")
    ]
    videos = []
    if video_ids:
        try:
            vids_resp = yt.videos().list(
                part="snippet,contentDetails,statistics",
                id=",".join(video_ids),
                maxResults=len(video_ids),
            ).execute()
            _quota.add(_quota.COST_LIST)
            videos = [
                _video_item(v)
                for v in vids_resp.get("items", [])
                if not _is_short(v.get("contentDetails", {}).get("duration", "PT0S"))
            ]
        except HttpError as e:
            _handle_yt_error(e)

    return {
        "items": videos,
        "next_page_token": resp.get("nextPageToken"),
        "has_more": "nextPageToken" in resp,
    }


# ── Video metadata ────────────────────────────────────────────────────────────

def get_video_metadata(access_token: str, video_id: str) -> dict:
    try:
        yt = _build_service(access_token)
        resp = yt.videos().list(
            part="snippet,contentDetails,statistics",
            id=video_id,
        ).execute()
        _quota.add(_quota.COST_LIST)
    except HttpError as e:
        _handle_yt_error(e)

    items = resp.get("items", [])
    if not items:
        raise HTTPException(status_code=404, detail="Video not found")

    video = items[0]
    result = _video_item(video)
    result["description"] = video.get("snippet", {}).get("description", "")
    return result


# ── Error helper ──────────────────────────────────────────────────────────────

def _handle_yt_error(e: HttpError) -> None:
    status = e.resp.status
    if status == 401:
        raise HTTPException(status_code=401, detail="Google token invalid or expired")
    if status == 403:
        raise HTTPException(status_code=403, detail="YouTube API quota exceeded or forbidden")
    raise HTTPException(status_code=502, detail=f"YouTube API error: {status}")
