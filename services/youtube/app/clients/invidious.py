"""Stream extraction via yt-dlp subprocess.

Running yt-dlp as a subprocess (not as an imported library) keeps the FastAPI
process lean at baseline (~50 MB). The subprocess is spawned only when a
stream request arrives, outputs JSON to stdout, then exits — freeing its memory.

Bot detection avoidance:
  - player_client=ios simulates an iOS client, bypassing most bot checks.
  - If /cookies/youtube.txt exists (Netscape format, mounted as a volume),
    it is passed via --cookies for additional auth context.
"""
import asyncio
import json
import logging
import os

from fastapi import HTTPException

logger = logging.getLogger(__name__)

_COOKIES_PATH = "/cookies/youtube.txt"


# No ffmpeg in the server image — never use merge selectors (bestvideo+bestaudio).
# Single-format selectors always resolve without external tools.
_PERMISSIVE_SELECTOR = "best[ext=mp4]/best[ext=webm]/best"


def _format_selector(quality: str | None) -> str:
    """Build a yt-dlp format selector for a server without ffmpeg.

    Prefers the requested quality height when provided, then falls back to
    the permissive selector so a stream is always returned for available videos.
    """
    if quality:
        import re
        m = re.match(r"(\d+)", quality)
        if m:
            h = m.group(1)
            return (
                f"best[height<={h}][ext=mp4]"
                f"/best[height<={h}][ext=webm]"
                f"/best[height<={h}]"
                f"/{_PERMISSIVE_SELECTOR}"
            )
    return _PERMISSIVE_SELECTOR


async def get_stream_url(video_id: str, quality: str | None = None) -> str:
    """Return a single playable URL for video_id using yt-dlp's format selector.

    Uses --get-url so yt-dlp resolves the best available format directly,
    avoiding the 'Requested format is not available' error that occurs when
    filtering manually against a stale or incomplete format list.
    """
    url = f"https://www.youtube.com/watch?v={video_id}"
    fmt = _format_selector(quality)

    cmd = [
        "yt-dlp",
        "--get-url",
        "--quiet",
        "--no-warnings",
        "-f", fmt,
        "--extractor-args", "youtube:player_client=ios,android,web",
    ]
    if os.path.exists(_COOKIES_PATH):
        cmd += ["--cookies", _COOKIES_PATH]
    cmd.append(url)

    try:
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await proc.communicate()
    except FileNotFoundError:
        raise HTTPException(status_code=500, detail="yt-dlp not installed")
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"yt-dlp subprocess error: {e}")

    if proc.returncode != 0:
        err = stderr.decode(errors="replace")[:500]
        logger.error("yt-dlp failed for %s (exit %d): %s", video_id, proc.returncode, err)
        raise HTTPException(status_code=502, detail=f"yt-dlp: {err}")

    if stderr_text := stderr.decode(errors="replace").strip():
        logger.warning("yt-dlp stderr for %s: %s", video_id, stderr_text[:300])

    # --get-url prints one URL per line; when merging two streams yt-dlp prints
    # two lines — we only proxy video (first line) in that case.
    stream_url = stdout.decode().strip().split("\n")[0]
    if not stream_url:
        raise HTTPException(status_code=404, detail="No playable stream found")

    logger.info("yt-dlp resolved stream for %s (quality=%s fmt=%s)", video_id, quality, fmt)
    return stream_url


async def get_streams(video_id: str) -> dict:
    url = f"https://www.youtube.com/watch?v={video_id}"

    cmd = [
        "yt-dlp",
        "--dump-json",
        "--quiet",
        "--no-warnings",
        "--extractor-args", "youtube:player_client=ios,android,web",
    ]
    if os.path.exists(_COOKIES_PATH):
        cmd += ["--cookies", _COOKIES_PATH]
    cmd.append(url)

    try:
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await proc.communicate()
    except FileNotFoundError:
        raise HTTPException(status_code=500, detail="yt-dlp not installed")
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"yt-dlp subprocess error: {e}")

    if proc.returncode != 0:
        err = stderr.decode(errors="replace")[:500]
        logger.error("yt-dlp failed for %s (exit %d): %s", video_id, proc.returncode, err)
        raise HTTPException(status_code=502, detail=f"yt-dlp: {err}")

    if stderr_text := stderr.decode(errors="replace").strip():
        logger.warning("yt-dlp stderr for %s: %s", video_id, stderr_text[:300])

    try:
        info = json.loads(stdout)
    except json.JSONDecodeError:
        raise HTTPException(status_code=502, detail="yt-dlp returned invalid JSON")

    formats = info.get("formats", [])
    logger.info(
        "yt-dlp %s: %d total formats (muxed=%d, video_only=%d, audio_only=%d, other=%d)",
        video_id,
        len(formats),
        sum(1 for f in formats if f.get("vcodec","none") != "none" and f.get("acodec","none") != "none"),
        sum(1 for f in formats if f.get("vcodec","none") != "none" and f.get("acodec","none") == "none"),
        sum(1 for f in formats if f.get("vcodec","none") == "none" and f.get("acodec","none") != "none"),
        sum(1 for f in formats if f.get("vcodec","none") == "none" and f.get("acodec","none") == "none"),
    )

    # Combined audio+video — directly playable in <video> / AVPlayer
    format_streams = [
        {
            "url": f["url"],
            "quality": f.get("format_note", ""),
            "ext": f.get("ext", ""),
            "filesize": f.get("filesize") or f.get("filesize_approx"),
        }
        for f in formats
        if f.get("vcodec", "none") != "none" and f.get("acodec", "none") != "none"
    ]

    # Audio-only adaptive streams
    audio_streams = [
        {
            "url": f["url"],
            "bitrate": f.get("abr", 0),
            "ext": f.get("ext", ""),
        }
        for f in formats
        if f.get("vcodec", "none") == "none" and f.get("acodec", "none") != "none"
    ]

    # Video-only adaptive streams
    video_streams = [
        {
            "url": f["url"],
            "quality": f.get("format_note", ""),
            "ext": f.get("ext", ""),
        }
        for f in formats
        if f.get("vcodec", "none") != "none" and f.get("acodec", "none") == "none"
    ]

    return {
        "format_streams": format_streams,
        "audio_streams": audio_streams,
        "video_streams": video_streams,
    }
