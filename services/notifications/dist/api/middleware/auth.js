/**
 * Extracts and verifies a JWT from the `token` query parameter.
 * Used during the WebSocket handshake (HTTP Upgrade request).
 * Throws on invalid token so Fastify returns 401.
 */
export function extractTokenFromQuery(request, verifier) {
    const { token } = request.query;
    if (!token) {
        throw Object.assign(new Error('Missing token'), { statusCode: 401 });
    }
    try {
        return verifier.verify(token);
    }
    catch {
        throw Object.assign(new Error('Invalid or expired token'), { statusCode: 401 });
    }
}
//# sourceMappingURL=auth.js.map