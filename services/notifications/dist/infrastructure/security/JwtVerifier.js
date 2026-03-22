import jwt from 'jsonwebtoken';
/**
 * Infrastructure — validates HS256 JWTs issued by the PHP service.
 * Uses the same JWT_SECRET so no inter-service credential exchange is needed.
 */
export class JwtVerifier {
    secret;
    constructor() {
        const secret = process.env['JWT_SECRET'];
        if (!secret)
            throw new Error('JWT_SECRET environment variable is required');
        this.secret = secret;
    }
    verify(token) {
        const decoded = jwt.verify(token, this.secret, { algorithms: ['HS256'] });
        if (typeof decoded === 'string') {
            throw new Error('Invalid token payload');
        }
        return decoded;
    }
}
//# sourceMappingURL=JwtVerifier.js.map