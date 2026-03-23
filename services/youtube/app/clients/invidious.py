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
import os

from fastapi import HTTPException

_COOKIES_PATH = "/cookies/youtube.txt"


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
        err = stderr.decode(errors="replace")[:300]
        raise HTTPException(status_code=502, detail=f"yt-dlp: {err}")

    try:
        info = json.loads(stdout)
    except json.JSONDecodeError:
        raise HTTPException(status_code=502, detail="yt-dlp returned invalid JSON")

    formats = info.get("formats", [])

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
