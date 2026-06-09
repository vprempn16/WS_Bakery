# Bakery WMS Backend API Documentation - Phase 1

This document outlines the API endpoints developed for Phase 1, focusing on Organization and User Management. All requests and responses wrap payload values within `{"data": {"values": ...}}` envelopes and use UUID keys.

---

## 1. Organization Management

### 1.1 Create Organization (and Initial Owner User)
* **Endpoint**: `POST /api/v1/organization/new`
* **Request Body**:
  ```json
  {
      "data": {
          "values": {
              "name": "Demo",
              "description": "",
              "firstUser": {
                  "firstName": "Arif",
                  "lastName": "imran1",
                  "email": "demoarif@example.com",
                  "phoneNumber": "+918012033834",
                  "password": "Admin@123",
                  "confirmPassword": "Admin@123"
              }
          }
      }
  }
  ```
* **Response (201 Created)**:
  ```json
  {
      "status": true,
      "message": "Organization created successfully.",
      "data": {
          "token": "48|atpen_935keHsWmMLJjnD9xEupme9IGxx88FYrtTqL6ekHe9bcdf41",
          "user": {
              "id": "5a080142-f939-45a3-a97a-b6287b1d0414",
              "first_name": "Arif",
              "last_name": "imran1",
              "email": "demoarif@example.com",
              "phone_number": "+918012033834",          
              "role": "owner",
              "organization": {
                  "id": "373c7c15-2b70-4710-a22a-9f24e7468777",
                  "name": "Demo"
              }
          }
      }
  }
  ```

### 1.2 Get Organization by ID
* **Endpoint**: `GET /api/v1/organization/{id}`
* **Response (200 OK)**:
  ```json
  {
      "data": {
          "values": {
              "id": "8010e670-7aa8-4370-90a3-f2c2e1fbdd88",
              "name": "WS Bakery",
              "description": "Bakery company",
              "email": "wsbakery12@gmail.com",
              "phone": "+91-9876543210",
              "address": "123 Main Street"
          }
      }
  }
  ```

### 1.3 Update Organization
* **Endpoint**: `PUT /api/v1/organization/{id}`
* **Request Body**: Same as Create Organization.
* **Response (200 OK)**: Returns the updated organization in the wrapped structure.

### 1.4 Delete Organization
* **Endpoint**: `DELETE /api/v1/organization/{id}`
* **Response (200 OK)**:
  ```json
  {
      "message": "Organization successfully deleted."
  }
  ```

### 1.5 Search Organizations
* **Endpoint**: `GET /api/v1/organization/search?query={keyword}`
* **Response (200 OK)**:
  ```json
  {
      "data": [
          {
              "values": {
                  "id": "8010e670-7aa8-4370-90a3-f2c2e1fbdd88",
                  "name": "WS Bakery",
                  "description": "Bakery company",
                  "email": "wsbakery12@gmail.com",
                  "phone": "+91-9876543210",
                  "address": "123 Main Street"
              }
          }
      ]
  }
  ```

---

## 2. User Management (under Settings)

### 2.1 Create User (including Auto-Login Token)
* **Endpoint**: `POST /api/v1/settings/User/new`
* **Request Body**:
  ```json
  {
      "data": {
          "values": {
              "lastName": "Nath",
              "firstName": "Prem",
              "role": "admin",
              "email": "premnath@atomlines.com",
              "phone": "+91-9876543210",
              "password": "Prem@2828",
              "confirmPassword": "Prem@2828",
              "organizationId": "8010e670-7aa8-4370-90a3-f2c2e1fbdd88"
          }
      }
  }
  ```
* **Response (201 Created)**:
  ```json
  {
      "data": {
          "values": {
              "id": "1ad3c780-e374-4b53-a551-ea62b322a36b",
              "firstName": "Prem",
              "lastName": "Nath",
              "email": "premnath@atomlines.com",
              "phone": "+91-9876543210",
              "role": "admin",
              "organizationId": "8010e670-7aa8-4370-90a3-f2c2e1fbdd88",
              "branchId": null,
              "token": "1|qXoY84tWlO..." // Sanctum bearer token to use for authorization
          }
      }
  }
  ```

### 2.2 Get User by ID
* **Endpoint**: `GET /api/v1/settings/User/{id}`
* **Response (200 OK)**:
  ```json
  {
      "data": {
          "values": {
              "id": "1ad3c780-e374-4b53-a551-ea62b322a36b",
              "firstName": "Prem",
              "lastName": "Nath",
              "email": "premnath@atomlines.com",
              "phone": "+91-9876543210",
              "role": "admin",
              "organizationId": "8010e670-7aa8-4370-90a3-f2c2e1fbdd88",
              "branchId": null
          }
      }
  }
  ```

### 2.3 Update User
* **Endpoint**: `PUT /api/v1/settings/User/{id}`
* **Request Body**:
  ```json
  {
      "data": {
          "values": {
              "lastName": "Nath Updated",
              "firstName": "Prem Updated",
              "role": "admin",
              "email": "updated@atomlines.com",
              "phone": "+91-9876543210",
              "organizationId": "8010e670-7aa8-4370-90a3-f2c2e1fbdd88"
          }
      }
  }
  ```
* **Response (200 OK)**: Returns the updated user details inside the wrapped structure.

### 2.4 Delete User
* **Endpoint**: `DELETE /api/v1/settings/User/{id}`
* **Response (200 OK)**:
  ```json
  {
      "message": "User successfully deleted."
  }
  ```

### 2.5 List Users
* **Endpoint**: `GET /api/v1/settings/User`
* **Optional Filter**: `GET /api/v1/settings/User?organizationId={UUID}`
* **Response (200 OK)**:
  ```json
  {
      "data": [
          {
              "values": {
                  "id": "1ad3c780-e374-4b53-a551-ea62b322a36b",
                  "firstName": "Prem",
                  "lastName": "Nath",
                  "email": "premnath@atomlines.com",
                  "phone": "+91-9876543210",
                  "role": "admin",
                  "organizationId": "8010e670-7aa8-4370-90a3-f2c2e1fbdd88",
                  "branchId": null
              }
          }
      ]
  }
  ```

---

## 3. Authentication

### 3.1 Login
* **Endpoint**: `POST /api/v1/auth/login`
* **Request Body**:
  ```json
  {
      "data": {
          "values": {
              "email": "demoarif@example.com",
              "password": "Admin@123"
          }
      }
  }
  ```
* **Response (200 OK)**:
  ```json
  {
      "status": true,
      "message": "Login successful.",
      "data": {
          "token": "49|newly_generated_token_here...",
          "user": {
              "id": "5a080142-f939-45a3-a97a-b6287b1d0414",
              "first_name": "Arif",
              "last_name": "imran1",
              "email": "demoarif@example.com",
              "phone_number": "+918012033834",          
              "role": "owner",
              "organization": {
                  "id": "373c7c15-2b70-4710-a22a-9f24e7468777",
                  "name": "Demo"
              }
          }
      }
  }
  ```

### 3.2 Logout
* **Endpoint**: `POST /api/v1/auth/logout`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
  ```json
  {
      "status": true,
      "message": "Logout successful."
  }
  ```
