# Secure Online Casino Platform - Development Guidelines

## Project Overview
A secure, scalable online casino platform with Web2/Web3 authentication, VIP benefits, provably fair games, and manual GCash payment processing.

## Tech Stack
- **Backend**: Laravel 11+
- **Database**: MySQL/PostgreSQL
- **Authentication**: JWT, OAuth2, MetaMask (Web3), Telegram
- **Frontend**: Vue.js/React (TBD)
- **Security**: Argon2, HTTPS/TLS, CAPTCHA

## Core Features
1. Multi-method authentication (Phone, MetaMask, Telegram, Guest)
2. VIP tier system with benefits
3. Provably fair in-house games (Dice, Hi-Lo, Mines, Plinko, Keno, Wheel, Pump, Crash)
4. Manual GCash payment processing
5. Bonus wagering system
6. Wallet management (real + bonus balance)
7. Admin dashboard with manual payment approval

## Development Standards
- Use Laravel best practices and conventions
- Implement atomic transactions for wallet operations
- All sensitive operations must be logged for audit
- Follow SOLID principles
- Write comprehensive tests for critical features
- Security-first approach for all implementations

## Security Requirements
- Argon2/bcrypt password hashing
- JWT token expiration and rotation
- CAPTCHA and rate limiting on auth endpoints
- IP whitelisting for admin panel
- No private key storage for Web3 wallets
- HTTPS/TLS encryption for all communications
- Input validation and sanitization

## Code Organization
- Controllers: Handle HTTP requests
- Services: Business logic layer
- Repositories: Data access layer
- Models: Eloquent ORM entities
- Middleware: Request filtering and authentication
- Jobs: Background tasks
- Events/Listeners: Event-driven architecture

## Database Standards
- Use migrations for schema changes
- Implement soft deletes where appropriate
- Add indexes for frequently queried columns
- Maintain immutable transaction logs
- Use UUID for sensitive identifiers

## Testing Requirements
- Unit tests for business logic
- Feature tests for API endpoints
- Integration tests for payment flows
- Test provably fair algorithms thoroughly

## Documentation
- API endpoints must be documented
- Complex algorithms need inline explanations
- Security considerations should be noted
- Payment flows require step-by-step docs
