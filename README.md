# Email Parser API

A Laravel-based API for parsing, storing, and managing email data with advanced text extraction capabilities.

## 🔗 Repository

[https://github.com/Olejs/emails-parser](https://github.com/Olejs/emails-parser)

---

## 📋 Table of Contents

- [Installation](#installation)
- [Running the Application](#running-the-application)
- [API Endpoints](#api-endpoints)
    - [Authentication](#authentication)
    - [Store Email](#store-email)
    - [Read Email](#read-email)
    - [Update Email](#update-email)
- [Request Examples](#request-examples)
- [Response Examples](#response-examples)

---

## 🚀 Installation

### Prerequisites

- PHP >= 8.1
- Composer
- MySQL/PostgreSQL database

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/Olejs/emails-parser.git
   cd emails-parser
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database in `.env`**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

---

## 🏃 Running the Application

### Local Development Server

Start the Laravel development server:

```bash
php artisan serve
```

The application will be available at `http://localhost:8000`

### Production Server

For production deployment, configure your web server (Nginx/Apache) to point to the `public` directory.

---

## 🔌 API Endpoints

Base URL: `http://142.93.189.58:8000/api`

### Authentication

#### Login

Authenticate and receive an access token.

**Endpoint:** `POST /auth/login`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "your_password"
}
```

**cURL Example:**
```bash
curl -X POST http://142.93.189.58:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "your_password"
  }'
```

**Success Response:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

---

### Store Email

Create a new email record with parsed content.

**Endpoint:** `POST /emails`

**Headers:**
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {your_access_token}
```

**Request Body:**
```json
{
  "affiliate_id": 999,
  "envelope": "{\"to\":[\"test@analyze.inflektion.ai\"],\"from\":\"sender@test.com\"}",
  "from": "Test Sender <sender@test.com>",
  "subject": "Test Email Subject",
  "dkim": "{@test.com : pass}",
  "SPF": "pass",
  "spam_score": 0.5,
  "email": "From: sender@test.com\r\nTo: test@analyze.inflektion.ai\r\nSubject: Test Email Subject\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nThis is a test email body.\r\n\r\nBest regards,\r\nTest Sender",
  "sender_ip": "192.168.1.100",
  "to": "test@analyze.inflektion.ai",
  "timestamp": 1709200000
}
```

**cURL Example:**
```bash
curl -i -X POST "http://142.93.189.58:8000/api/emails" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer your_access_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "affiliate_id": 999,
    "envelope": "{\"to\":[\"test@analyze.inflektion.ai\"],\"from\":\"sender@test.com\"}",
    "from": "Test Sender <sender@test.com>",
    "subject": "Test Email Subject",
    "dkim": "{@test.com : pass}",
    "SPF": "pass",
    "spam_score": 0.5,
    "email": "From: sender@test.com\r\nTo: test@analyze.inflektion.ai\r\nSubject: Test Email Subject\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nThis is a test email body.\r\n\r\nBest regards,\r\nTest Sender",
    "sender_ip": "192.168.1.100",
    "to": "test@analyze.inflektion.ai",
    "timestamp": 1709200000
  }'
```

**Success Response:**
```json
{
  "id": 406,
  "affiliate_id": 999,
  "from": "Test Sender <sender@test.com>",
  "to": "test@analyze.inflektion.ai",
  "subject": "Test Email Subject",
  "raw_text": "This is a test email body.\n\nBest regards,\nTest Sender",
  "created_at": "2024-03-01T12:00:00.000000Z",
  "updated_at": "2024-03-01T12:00:00.000000Z"
}
```

---

### Read Email

Retrieve a specific email by ID.

**Endpoint:** `GET /emails/{id}`

**Headers:**
```
Accept: application/json
Authorization: Bearer {your_access_token}
```

**cURL Example:**
```bash
curl -i -X GET "http://142.93.189.58:8000/api/emails/406" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer your_access_token_here"
```

**Success Response:**
```json
{
  "id": 406,
  "affiliate_id": 999,
  "envelope": "{\"to\":[\"test@analyze.inflektion.ai\"],\"from\":\"sender@test.com\"}",
  "from": "Test Sender <sender@test.com>",
  "subject": "Test Email Subject",
  "dkim": "{@test.com : pass}",
  "SPF": "pass",
  "spam_score": 0.5,
  "email": "From: sender@test.com...",
  "sender_ip": "192.168.1.100",
  "to": "test@analyze.inflektion.ai",
  "timestamp": 1709200000,
  "raw_text": "This is a test email body.\n\nBest regards,\nTest Sender",
  "created_at": "2024-03-01T12:00:00.000000Z",
  "updated_at": "2024-03-01T12:00:00.000000Z"
}
```

---

### Update Email

Update an existing email record.

**Endpoint:** `PUT /emails/{id}` or `PATCH /emails/{id}`

**Headers:**
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {your_access_token}
```

**Request Body** (all fields are optional):
```json
{
  "affiliate_id": 1000,
  "envelope": "{\"to\":[\"updated@example.com\"],\"from\":\"sender@test.com\"}",
  "from": "Updated Sender <sender@test.com>",
  "subject": "Updated Subject",
  "dkim": "{@test.com : pass}",
  "SPF": "pass",
  "spam_score": 0.3,
  "email": "Updated email content...",
  "sender_ip": "192.168.1.101",
  "to": "updated@example.com",
  "timestamp": 1709200100,
  "raw_text": "Updated plain text content"
}
```

**cURL Example:**
```bash
curl -i -X PUT "http://142.93.189.58:8000/api/emails/406" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer your_access_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "Updated Subject",
    "spam_score": 0.3
  }'
```

**Validation Rules:**

| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `affiliate_id` | integer | optional | min: 1 |
| `envelope` | string | optional | - |
| `from` | string | optional | max: 255 |
| `subject` | string | optional | max: 1000 |
| `dkim` | string | nullable | max: 255 |
| `SPF` | string | nullable | max: 255 |
| `spam_score` | numeric | nullable | min: 0, max: 100 |
| `email` | string | optional | - |
| `sender_ip` | string | nullable | valid IP address |
| `to` | string | optional | - |
| `timestamp` | integer | optional | min: 0 |
| `raw_text` | string | nullable | - |

**Success Response:**
```json
{
  "id": 406,
  "affiliate_id": 1000,
  "subject": "Updated Subject",
  "spam_score": 0.3,
  "updated_at": "2024-03-01T12:30:00.000000Z"
}
```

---

## 📝 Request Examples

### Complete Workflow Example

```bash
# 1. Login and get token
TOKEN=$(curl -s -X POST http://142.93.189.58:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}' \
  | jq -r '.access_token')

# 2. Store new email
EMAIL_ID=$(curl -s -X POST http://142.93.189.58:8000/api/emails \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "affiliate_id": 999,
    "from": "sender@test.com",
    "to": "recipient@test.com",
    "subject": "Test",
    "email": "Test content",
    "timestamp": 1709200000
  }' | jq -r '.id')

# 3. Read the email
curl -X GET "http://142.93.189.58:8000/api/emails/$EMAIL_ID" \
  -H "Authorization: Bearer $TOKEN"

# 4. Update the email
curl -X PUT "http://142.93.189.58:8000/api/emails/$EMAIL_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"spam_score": 0.1}'
```

---

## 🛠️ Features

- **Advanced Email Parsing**: Supports multipart MIME, base64, quoted-printable encoding
- **HTML to Plain Text**: Automatic conversion with proper formatting preservation
- **Authentication**: Token-based API authentication
- **Validation**: Comprehensive request validation
- **Error Handling**: Detailed error messages and logging

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## 📧 Support

For issues and questions, please use the [GitHub Issues](https://github.com/Olejs/emails-parser/issues) page.
