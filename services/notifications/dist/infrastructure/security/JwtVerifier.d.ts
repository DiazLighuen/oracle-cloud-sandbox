export interface JwtPayload {
    sub: string;
    email: string;
    name: string;
    avatar: string;
    is_admin: boolean;
}
/**
 * Infrastructure — validates HS256 JWTs issued by the PHP service.
 * Uses the same JWT_SECRET so no inter-service credential exchange is needed.
 */
export declare class JwtVerifier {
    private readonly secret;
    constructor();
    verify(token: string): JwtPayload;
}
//# sourceMappingURL=JwtVerifier.d.ts.map