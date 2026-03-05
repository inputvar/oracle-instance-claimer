# OCI Instance Claimer (oc-ni)

A PHP application for automatically launching OCI (Oracle Cloud Infrastructure) instances, deployed on Fly.io.

## Quick Start

### 1. Clone the Repository
```bash
git clone <your-repo-url>
cd oc-ni
```

### 2. Configure Credentials
```bash
cd app
cp .env.example .env
```

Edit `app/.env` with your OCI credentials:
- `OCI_REGION`, `OCI_USER_ID`, `OCI_TENANCY_ID`, `OCI_KEY_FINGERPRINT` - from OCI API keys
- `OCI_PRIVATE_KEY_FILENAME` - path to your downloaded .pem key file
- `OCI_SUBNET_ID`, `OCI_IMAGE_ID` - from OCI console
- `OCI_SSH_PUBLIC_KEY` - your SSH public key for instance access
- `SMTP_*` and `NOTIFY_EMAIL` - optional email notifications

### 3. Install Dependencies
```bash
cd app
composer install
```

### 4. Run Locally

**Option A: Direct PHP**
```bash
php -S localhost:8080
# Visit http://localhost:8080
```

**Option B: Docker**
```bash
docker build -t oc-ni .
docker run -p 8080:8080 --env-file app/.env oc-ni
```

### 5. Deploy to Fly.io

```bash
flyctl deploy
flyctl secrets set OCI_REGION=ap-mumbai-1
# Set other secrets as needed
```

## Security ⚠️

**Never commit these files:**
- `app/.env` - Contains your OCI credentials
- `*.pem` - OCI private key files
- `ssh-key-*` - SSH key files

These are protected by `.gitignore`. Use `app/.env.example` as a template.

## Project Layout
```
oc-ni/
├── app/                    # Application code
│   ├── .env.example       # Configuration template
│   ├── .env               # Your credentials (NOT tracked)
│   ├── index.php          # Main app
│   ├── loop.php           # Background task
│   ├── composer.json      # Dependencies
│   └── src/               # Application code
├── Dockerfile             # Docker configuration
├── fly.toml              # Fly.io deployment config
└── .gitignore            # Ignore sensitive files
```

## Configuration Reference

See `app/.env.example` for all available options and descriptions.

## Troubleshooting

- **OCI auth fails**: Verify `.env` credentials and private key path is absolute
- **"Too many requests"**: API rate limiting - app will retry after `TOO_MANY_REQUESTS_TIME_WAIT` seconds
- **Docker build fails**: Ensure private key file path exists and is accessible
