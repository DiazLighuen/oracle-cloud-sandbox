import os
from typing import Optional

from fastapi import HTTPException, Request, Security
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from jose import JWTError, jwt

# auto_error=False so we can fall back to the cookie when the header is absent
_bearer = HTTPBearer(auto_error=False)
_JWT_SECRET = os.environ["JWT_SECRET"]
_ALGORITHM = "HS256"


async def verify_jwt(
    request: Request,
    credentials: Optional[HTTPAuthorizationCredentials] = Security(_bearer),
) -> dict:
    """Accept JWT from Authorization header (API/iOS) or 'jwt' cookie (browser <video src>)."""
    token = credentials.credentials if credentials else request.cookies.get("jwt")
    if not token:
        raise HTTPException(status_code=401, detail="Missing token")
    try:
        return jwt.decode(token, _JWT_SECRET, algorithms=[_ALGORITHM])
    except JWTError:
        raise HTTPException(status_code=401, detail="Invalid or expired token")
