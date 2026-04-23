# OhoSearch System Design

## 📊 Updated ER Diagram

```mermaid
erDiagram
    USERS ||--o{ BUSINESSES : owns
    USERS ||--o{ REVIEWS : writes
    USERS ||--o{ FAVORITES : has
    USERS ||--o{ CLAIM_REQUESTS : makes
    USERS ||--o{ COUPON_REQUESTS : makes
    USERS ||--o{ PAYMENTS : makes
    USERS ||--o{ SUPPORT_TICKETS : raises
    USERS ||--o{ NOTIFICATIONS : receives

    CATEGORIES }o--o{ BUSINESSES : categorized-in
    CATEGORIES ||--o{ CATEGORIES : parent-child

    BUSINESSES ||--o{ WORKING_HOURS : has
    BUSINESSES ||--o{ BUSINESS_GALLERY : has
    BUSINESSES ||--o{ ACHIEVEMENTS : showcases
    BUSINESSES ||--o{ OFFERINGS : provides
    BUSINESSES ||--o{ REVIEWS : receives
    BUSINESSES ||--o{ CLAIM_REQUESTS : targets
    BUSINESSES ||--o{ FAVORITES : bookmarked-in
    BUSINESSES ||--o{ ENQUIRIES : receives
    BUSINESSES ||--o{ FEATURED_LISTINGS : featured-as
    BUSINESSES ||--o{ PAYMENTS : paid-for

    OFFERINGS ||--o{ FAVORITES : bookmarked-as

    COUPONS ||--o{ PAYMENTS : applies-to
    USERS ||--o{ COUPONS : assigned-to
    BUSINESSES ||--o{ COUPONS : for-business

    PAYMENTS ||--|| FEATURED_LISTINGS : activates
```

## 🔄 Sequence Diagrams

### 🎟️ Coupon Request & Usage

```mermaid
sequenceDiagram
    participant C as Contributor/Owner
    participant A as Admin
    participant DB as Database
    participant N as Notification Service

    C->>A: Request Coupon (Purpose: Featured Listing)
    A->>DB: Store Coupon Request (Status: Pending)
    A->>A: Review Request
    alt Approved
        A->>DB: Generate Coupon Code & Assign to User
        A->>DB: Update Request Status (Approved)
        DB-->>A: Success
        A->>N: Trigger Notification (Coupon Granted)
        N-->>C: SMS/Email: Your coupon is ready!
    else Rejected
        A->>DB: Update Request Status (Rejected)
        A->>N: Trigger Notification (Request Rejected)
    end

    Note over C, DB: Usage Flow
    C->>DB: Apply Coupon during Payment
    DB->>DB: Validate Coupon (Assigned?, Not Used?, Not Expired?)
    DB-->>C: Coupon Applied (Discounted Amount)
```

### 🤝 Business Claiming Flow

```mermaid
sequenceDiagram
    participant U as User (Contributor)
    participant A as Admin
    participant DB as Database

    U->>DB: Submit Claim Request (Business ID, Proof/Reason)
    A->>DB: List Pending Claim Requests
    A->>A: Verify Request
    alt Approved
        A->>DB: Update Business (Set owner_id = requester_id)
        A->>DB: Update Claim Status (Approved)
        DB-->>U: You now own this business!
    else Rejected
        A->>DB: Update Claim Status (Rejected)
    end
```

### 💰 Payment & Featured Activation

```mermaid
sequenceDiagram
    participant O as Business Owner
    participant P as Payment Gateway
    participant B as Backend Service
    participant DB as Database

    O->>B: Purchase Featured Plan (Coupon Applied?)
    B->>P: Initialize Transaction
    P-->>O: Payment UI
    O->>P: Complete Payment
    P-->>B: Webhook: Payment Success
    B->>DB: Record Payment (Status: Completed)
    B->>DB: Insert/Update Featured Listing (Start/End Date)
    B->>DB: Mark Coupon as Used (If applicable)
    B-->>O: Success: Listing is now Featured!
```

## 📡 API Design

### 🎟️ Coupon Service
- `POST /api/coupons/request`: Raise a new coupon request.
- `GET /api/admin/coupons/requests`: List all pending requests (Admin).
- `POST /api/admin/coupons/approve/{id}`: Approve request and generate code.
- `GET /api/my-coupons`: List active coupons for the logged-in user.

### 💰 Payment & Ads Service
- `GET /api/featured-plans`: List available promotion plans.
- `POST /api/payments/initiate`: Start a payment for a featured plan.
- `POST /api/payments/webhook`: Handle gateway callbacks.
- `GET /api/admin/payments`: Monitor all transactions.

### 🔍 Search & Interaction Service
- `GET /api/search`: Query businesses by keyword, category, and location (Haversine).
- `POST /api/businesses/{id}/enquiry`: Send message to business.
- `POST /api/businesses/{id}/favorite`: Toggle favorite status.
- `POST /api/businesses/{id}/review`: Submit rating and review.
- `POST /api/reviews/{id}/reply`: Reply to a review (Owner only).
- `POST /api/businesses/{id}/claim`: Submit a claim request.

### 🛠️ Support Service
- `POST /api/support/tickets`: Create a help ticket.
- `GET /api/support/tickets`: View my tickets.
- `PATCH /api/admin/support/tickets/{id}`: Update ticket status/reply.

## 🧩 Microservices Architecture Impact

To scale, the system can be split into:
1. **Identity Service**: User management, Auth, RBAC.
2. **Directory Service**: Businesses, Categories, Offerings, Search.
3. **Billing Service**: Payments, Coupons, Featured Listing logic.
4. **Engagement Service**: Reviews, Favorites, Enquiries.
5. **Support Service**: Tickets, Notifications.

**Shared Components**:
- **API Gateway**: Routing, Rate Limiting, JWT Verification.
- **Message Broker (Redis/RabbitMQ)**: For async events like Notification triggers, Analytics updates, and Featured expiry handling.

## 🔔 Notification & Alert Flow
- **Real-time**: WebSocket (for Chat/Enquiries).
- **Push/Async**:
  - `expiry_alert`: Triggered 3 days before featured listing ends.
  - `approval_update`: Triggered when Admin approves/rejects listing or coupon.
  - `new_enquiry`: Alert owner when a customer sends a message.

## 📊 Event Tracking
- `listing_view`: Increment view count in analytics table.
- `enquiry_sent`: Track conversion rate (Enquiries / Views).
- `featured_impression`: Track performance of paid listings.
- `search_query`: Log keywords for popular demand analysis.
