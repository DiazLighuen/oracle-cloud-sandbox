import type { FastifyRequest } from 'fastify';
import type { JwtVerifier, JwtPayload } from '../../infrastructure/security/JwtVerifier.js';
/** Augment Fastify's request type so callers have full type safety. */
declare module 'fastify' {
    interface FastifyRequest {
        jwtUser?: JwtPayload;
    }
}
/**
 * Extracts and verifies a JWT from the `token` query parameter.
 * Used during the WebSocket handshake (HTTP Upgrade request).
 * Throws on invalid token so Fastify returns 401.
 */
export declare function extractTokenFromQuery(request: FastifyRequest, verifier: JwtVerifier): JwtPayload;
//# sourceMappingURL=auth.d.ts.map