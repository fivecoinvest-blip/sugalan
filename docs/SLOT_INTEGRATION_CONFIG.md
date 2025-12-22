# Slot Game Integration Configuration

## Provider: SoftAPI

### API Credentials
- **Token**: `5cd0be9827c469e7ce7d07abbb239e98`
- **Secret**: `dc6b955933342d32d49b84c52b59184f`
- **Encryption**: AES-256-ECB (Enabled)
- **Callback Encryption**: Enabled

### API Endpoints
- **Base URL**: `https://igamingapis.live/api/v1`
- **Provider List**: `https://igamingapis.com/provider/`
- **Games by Provider**: `https://igamingapis.com/provider/brands.php?brand_id={ID}`

### Integration Features
- Multi-provider slot games (JILI, PG Soft, etc.)
- Encrypted communication
- Real-time callback for bet results
- Multi-language support
- Multi-currency support

### Security
- All payloads encrypted with AES-256-ECB
- 32-byte secret key required
- Callback encryption enabled
- Timestamp validation

### Documentation
Full API documentation available at: `docs/API_Documentation_2025-12-22.html`

---

**Status**: Ready for implementation
**Date Added**: December 22, 2025
