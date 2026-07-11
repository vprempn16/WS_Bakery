# Bakery WMS Backend API Documentation - Phase 1

This document outlines the API endpoints developed for Phase 1, focusing on Organization and User Management. All requests and responses wrap payload values within `{"data": {"values": ...}}` envelopes and use UUID keys.

---

## 1. Organization Management

### 1.1 Create Organization (and Initial Owner User)

**Create Organization (and Initial Owner User) starting**
* **Endpoint**: `POST /api/v1/Organization/new`
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

**Create Organization (and Initial Owner User) ending**


### 1.2 Get Organization by ID

**Get Organization by ID starting**
* **Endpoint**: `GET /api/v1/Organization/{id}`
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

**Get Organization by ID ending**


### 1.3 Update Organization

**Update Organization starting**
* **Endpoint**: `POST /api/v1/Organization/{id}`
* **Request Body**: Same as Create Organization.
* **Response (200 OK)**: Returns the updated organization in the wrapped structure.

**Update Organization ending**


### 1.4 Delete Organization

**Delete Organization starting**
* **Endpoint**: `DELETE /api/v1/Organization/{id}`
* **Response (200 OK)**:
  ```json
  {
      "message": "Organization successfully deleted."
  }
  ```

**Delete Organization ending**


### 1.5 Search Organizations

**Search Organizations starting**
* **Endpoint**: `GET /api/v1/Organization/search?query={keyword}`
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

**Search Organizations ending**


---

## 2. User Management (under Settings)

### 2.1 Create User (including Auto-Login Token)

**Create User (including Auto-Login Token) starting**
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

**Create User (including Auto-Login Token) ending**


### 2.2 Get User by ID

**Get User by ID starting**
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

**Get User by ID ending**


### 2.3 Update User

**Update User starting**
* **Endpoint**: `POST /api/v1/settings/User/{id}`
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

**Update User ending**


### 2.4 Delete User

**Delete User starting**
* **Endpoint**: `DELETE /api/v1/settings/User/{id}`
* **Response (200 OK)**:
  ```json
  {
      "message": "User successfully deleted."
  }
  ```

**Delete User ending**


### 2.5 List Users

**List Users starting**
* **Endpoint**: `GET /api/v1/settings/User`
* **Headers**: `Authorization: Bearer {token}`
* **Optional Filters**:
  * Filter by Role: `GET /api/v1/settings/User?role={owner|admin|staff}`
  * Search by name/email: `GET /api/v1/settings/User?search={keyword}`
  * Combined: `GET /api/v1/settings/User?role={role}&search={keyword}`
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

**List Users ending**


---

## 3. Authentication

### 3.1 Login

**Login starting**
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

**Login ending**


### 3.2 Logout

**Logout starting**
* **Endpoint**: `POST /api/v1/auth/logout`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
  ```json
  {
      "status": true,
      "message": "Logout successful."
  }
  ```

**Logout ending**


## 4. Ingredient Management

### 4.1 List Ingredients

**List Ingredients starting**
* **Endpoint**: `GET /api/v1/Ingredient`
* **Headers**: `Authorization: Bearer {token}`
* **Optional Filters**:
  * Search by name: `GET /api/v1/Ingredient?search={keyword}`
  * Filter by Vendor: `GET /api/v1/Ingredient?vendorId={vendor_uuid}`
  * Filter by Stock Status: `GET /api/v1/Ingredient?stockStatus={low|in_stock}`
  * Combined: `GET /api/v1/Ingredient?search={keyword}&vendorId={vendor_uuid}&stockStatus={low}`
* **Response (200 OK)**:
```json
{
    "data": [
        {
            "values": {
                "id": "uuid",
                "organizationId": "uuid",
                "vendorId": null,
                "name": "Flour",
                "unit": "g",
                "minimumStockLevel": 0,
                "currentStock": 1500
            }
        }
    ]
}
```

**List Ingredients ending**


### 4.2 Create Ingredient

**Create Ingredient starting**
* **Endpoint**: `POST /api/v1/Ingredient/new`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "vendorId": "{{vendor_uuid}}",
            "name": "Sugar",
            "unit": "g",
            "minimumStockLevel": 500,
            "currentStock": 2000
        }
    }
}
```
* **Response (201 Created)**:
```json
{
    "data": {
        "values": {
            "id": "new_uuid",
            "organizationId": "{{org_uuid}}",
            "vendorId": "{{vendor_uuid}}",
            "name": "Sugar",
            "unit": "g",
            "minimumStockLevel": 500,
            "currentStock": 2000
        }
    }
}
```

* **Additional Request Body Examples**:

**Example: All-Purpose Flour (Staple)**
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "vendorId": "{{vendor_uuid}}",
            "name": "All-Purpose Flour",
            "unit": "kg",
            "minimumStockLevel": 50,
            "currentStock": 200
        }
    }
}
```

**Example: Unsalted Butter (Dairy)**
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "vendorId": "{{vendor_uuid}}",
            "name": "Unsalted Butter",
            "unit": "kg",
            "minimumStockLevel": 10,
            "currentStock": 25
        }
    }
}
```

**Example: Active Dry Yeast (Leavening Agent)**
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "vendorId": "{{vendor_uuid}}",
            "name": "Active Dry Yeast",
            "unit": "g",
            "minimumStockLevel": 500,
            "currentStock": 2000
        }
    }
}
```

**Example: Dark Chocolate Chips (Add-ins)**
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "vendorId": "{{vendor_uuid}}",
            "name": "Dark Chocolate Chips",
            "unit": "kg",
            "minimumStockLevel": 5,
            "currentStock": 15
        }
    }
}
```

**Create Ingredient ending**


### 4.3 Get Ingredient by ID

**Get Ingredient by ID starting**
* **Endpoint**: `GET /api/v1/Ingredient/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**: Same structure as single element in list response.

**Get Ingredient by ID ending**


### 4.4 Update Ingredient

**Update Ingredient starting**
* **Endpoint**: `POST /api/v1/Ingredient/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**: Same as Create but fields to update.
* **Response (200 OK)**: Updated ingredient object.

**Update Ingredient ending**


### 4.5 Delete Ingredient

**Delete Ingredient starting**
* **Endpoint**: `DELETE /api/v1/Ingredient/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{ "message": "Ingredient successfully deleted." }
```

**Delete Ingredient ending**


### 4.6 Low Stock Endpoint

**Low Stock Endpoint starting**
* **Endpoint**: `GET /api/v1/Ingredient/low-stock`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**: List of ingredients where `current_stock` < `minimum_stock_level`.

**Low Stock Endpoint ending**


---

## 5. Vendor Management

### 5.1 List Vendors

**List Vendors starting**
* **Endpoint**: `GET /api/v1/Vendor`
* **Headers**: `Authorization: Bearer {token}`
* **Optional Filters**:
  * Search by name, contact person, email, or phone: `GET /api/v1/Vendor?search={keyword}`
* **Response (200 OK)**:
```json
{
    "data": [
        {
            "values": {
                "id": "uuid",
                "organizationId": "uuid",
                "name": "Global Flour Corp",
                "contactPerson": "Jane Smith",
                "phone": "+1-555-0199",
                "email": "contact@globalflour.com",
                "address": "789 Mill Road, Grain Valley",
                "createdAt": "2026-06-11T21:31:35.000000Z",
                "updatedAt": "2026-06-11T21:31:35.000000Z"
            }
        }
    ]
}
```

**List Vendors ending**


### 5.2 Create Vendor

**Create Vendor starting**
* **Endpoint**: `POST /api/v1/Vendor/new`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "name": "Global Flour Corp",
            "contactPerson": "Jane Smith",
            "phone": "+1-555-0199",
            "email": "contact@globalflour.com",
            "address": "789 Mill Road, Grain Valley"
        }
    }
}
```
* **Response (201 Created)**:
```json
{
    "data": {
        "values": {
            "id": "new_vendor_uuid",
            "organizationId": "{{org_uuid}}",
            "name": "Global Flour Corp",
            "contactPerson": "Jane Smith",
            "phone": "+1-555-0199",
            "email": "contact@globalflour.com",
            "address": "789 Mill Road, Grain Valley",
            "createdAt": "2026-06-11T21:31:35.000000Z",
            "updatedAt": "2026-06-11T21:31:35.000000Z"
        }
    }
}
```

**Create Vendor ending**


### 5.3 Get Vendor by ID

**Get Vendor by ID starting**
* **Endpoint**: `GET /api/v1/Vendor/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**: Same structure as single element in list response.

**Get Vendor by ID ending**


### 5.4 Update Vendor

**Update Vendor starting**
* **Endpoint**: `POST /api/v1/Vendor/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "name": "Global Flour Corp Updated",
            "contactPerson": "Jane Smith",
            "phone": "+1-555-0199",
            "email": "contact@globalflour.com",
            "address": "789 Mill Road, Grain Valley"
        }
    }
}
```
* **Response (200 OK)**:
```json
{
    "data": {
        "values": {
            "id": "vendor_uuid",
            "organizationId": "{{org_uuid}}",
            "name": "Global Flour Corp Updated",
            "contactPerson": "Jane Smith",
            "phone": "+1-555-0199",
            "email": "contact@globalflour.com",
            "address": "789 Mill Road, Grain Valley",
            "createdAt": "2026-06-11T21:31:35.000000Z",
            "updatedAt": "2026-06-11T21:31:35.000000Z"
        }
    }
}
```

**Update Vendor ending**


### 5.5 Delete Vendor

**Delete Vendor starting**
* **Endpoint**: `DELETE /api/v1/Vendor/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "message": "Vendor successfully deleted."
}
```

**Delete Vendor ending**


---

## 6. Inventory Transactions

### 6.1 List Inventory Transactions

**List Inventory Transactions starting**
* **Endpoint**: `GET /api/v1/InventoryTransaction`
* **Headers**: `Authorization: Bearer {token}`
* **Optional Filters**:
  * Filter by Ingredient: `GET /api/v1/InventoryTransaction?ingredientId={ingredient_uuid}`
  * Filter by Transaction Type: `GET /api/v1/InventoryTransaction?type={in|out|waste|production}`
  * Date Range Filters: `GET /api/v1/InventoryTransaction?startDate={YYYY-MM-DD}&endDate={YYYY-MM-DD}`
  * Combined: `GET /api/v1/InventoryTransaction?ingredientId={ingredient_uuid}&type=waste&startDate=2026-06-01&endDate=2026-06-15`
* **Response (200 OK)**:
```json
{
    "data": [
        {
            "values": {
                "id": "uuid",
                "organizationId": "uuid",
                "ingredientId": "uuid",
                "type": "in",
                "quantity": 1000,
                "referenceNote": "Purchased 1kg Sugar",
                "createdAt": "2026-06-11T21:31:35.000000Z"
            }
        }
    ]
}
```

**List Inventory Transactions ending**


### 6.2 Create Inventory Transaction

**Create Inventory Transaction starting**
* **Endpoint**: `POST /api/v1/InventoryTransaction/new`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
*(Note: `type` must be one of: `in`, `out`, `waste`, `production`)*
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "ingredientId": "{{ingredient_uuid}}",
            "type": "in",
            "quantity": 1000,
            "referenceNote": "Purchased 1kg Sugar"
        }
    }
}
```
* **Response (201 Created)**:
```json
{
    "data": {
        "values": {
            "id": "new_transaction_uuid",
            "organizationId": "{{org_uuid}}",
            "ingredientId": "{{ingredient_uuid}}",
            "type": "in",
            "quantity": 1000.0,
            "referenceNote": "Purchased 1kg Sugar",
            "createdAt": "2026-06-11T21:31:35.000000Z"
        }
    }
}
```

**Create Inventory Transaction ending**


### 6.3 Get Inventory Transaction by ID

**Get Inventory Transaction by ID starting**
* **Endpoint**: `GET /api/v1/InventoryTransaction/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Success",
    "data": {
        "fields": [
            {
                "fieldname": "id",
                "fieldlabel": "ID"
            },
            {
                "fieldname": "ingredientId",
                "fieldlabel": "Ingredient ID"
            },
            {
                "fieldname": "type",
                "fieldlabel": "Type"
            },
            {
                "fieldname": "quantity",
                "fieldlabel": "Quantity"
            },
            {
                "fieldname": "referenceNote",
                "fieldlabel": "Reference Note"
            },
            {
                "fieldname": "createdAt",
                "fieldlabel": "Created At"
            }
        ],
        "values": {
            "id": "transaction_uuid",
            "organizationId": "org_uuid",
            "ingredientId": "ingredient_uuid",
            "type": "in",
            "quantity": 1000.0,
            "referenceNote": "Purchased 1kg Sugar",
            "createdAt": "2026-06-11T21:31:35.000000Z"
        }
    }
}
```

**Get Inventory Transaction by ID ending**


---

## 7. Product Management

### 7.1 List Products

**List Products starting**
* **Endpoint**: `GET /api/v1/Product`
* **Headers**: `Authorization: Bearer {token}`
* **Optional Filters**:
  * Search by name or product number: `GET /api/v1/Product?search={keyword_or_product_number}`
  * Filter by Unit: `GET /api/v1/Product?unit={pcs|kg|g|l|ml|pkt}`
  * Filter by Stock Status: `GET /api/v1/Product?stockStatus={out_of_stock|in_stock}`
  * Combined: `GET /api/v1/Product?search=Bread&unit=pcs&stockStatus=in_stock`
* **Response (200 OK)**:
```json
{
    "data": [
        {
            "values": {
                "id": "uuid",
                "organizationId": "uuid",
                "productNumber": "PROD1",
                "name": "Sweet Bread",
                "description": "Delicious baked sweet bread",
                "price": 50,
                "unit": "pcs",
                "shelfLifeDays": 3,
                "currentStock": 0,
                "createdAt": "2026-06-11T21:31:35.000000Z",
                "updatedAt": "2026-06-11T21:31:35.000000Z"
            }
        }
    ]
}
```

**List Products ending**


### 7.2 Create Product

**Create Product starting**
* **Endpoint**: `POST /api/v1/Product/new`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
*(Note: `productNumber` can be provided manually, or it will be auto-generated as `PROD1`, `PROD2`, etc. `unit` must be one of: `Piece`, `Box`, `Packet`, `Gram`, `Dozen`, `Liter`, `ml`, `l`. Defaults to `Piece` if not provided)*
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "name": "Sweet Bread",
            "description": "Delicious baked sweet bread",
            "price": 50,
            "unit": "Piece",
            "shelfLifeDays": 3
        }
    }
}
```
* **Response (201 Created)**:
```json
{
    "data": {
        "values": {
            "id": "new_product_uuid",
            "organizationId": "{{org_uuid}}",
            "productNumber": "PROD1",
            "name": "Sweet Bread",
            "description": "Delicious baked sweet bread",
            "price": 50,
            "unit": "Piece",
            "shelfLifeDays": 3,
            "currentStock": 0,
            "createdAt": "2026-06-11T21:31:35.000000Z",
            "updatedAt": "2026-06-11T21:31:35.000000Z"
        }
    }
}
```

**Create Product ending**


### 7.3 Get Product by ID

**Get Product by ID starting**
* **Endpoint**: `GET /api/v1/Product/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**: Same structure as single element in list response.

**Get Product by ID ending**


### 7.4 Update Product

**Update Product starting**
* **Endpoint**: `POST /api/v1/Product/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "name": "Sweet Bread Updated",
            "description": "Super soft sweet bread",
            "price": 55,
            "unit": "Piece",
            "shelfLifeDays": 4
        }
    }
}
```
* **Response (200 OK)**:
```json
{
    "data": {
        "values": {
            "id": "product_uuid",
            "organizationId": "{{org_uuid}}",
            "productNumber": "PROD1",
            "name": "Sweet Bread Updated",
            "description": "Super soft sweet bread",
            "price": 55,
            "unit": "Piece",
            "shelfLifeDays": 4,
            "currentStock": 0,
            "createdAt": "2026-06-11T21:31:35.000000Z",
            "updatedAt": "2026-06-11T21:31:35.000000Z"
        }
    }
}
```

**Update Product ending**


### 7.5 Delete Product

**Delete Product starting**
* **Endpoint**: `DELETE /api/v1/Product/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "message": "Product successfully deleted."
}
```

**Delete Product ending**


---

## 8. Recipe Management

### 8.1 List Recipe Ingredients for a Product

**List Recipe Ingredients for a Product starting**
* **Endpoint**: `GET /api/v1/Product/{productId}/recipe`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Success",
    "data": [
        {
            "values": {
                "id": "recipe_uuid",
                "productId": "product_uuid",
                "ingredientId": "ingredient_uuid",
                "quantityRequired": 200.0,
                "ingredient": {
                    "values": {
                        "id": "ingredient_uuid",
                        "organizationId": "org_uuid",
                        "vendorId": "vendor_uuid",
                        "name": "Sugar",
                        "unit": "g",
                        "minimumStockLevel": 500.0,
                        "currentStock": 2000.0,
                        "createdAt": "2026-06-11T21:31:35.000000Z",
                        "updatedAt": "2026-06-11T21:31:35.000000Z"
                    }
                }
            }
        }
    ]
}
```

**List Recipe Ingredients for a Product ending**


### 8.2 Add or Update Recipe Ingredient for a Product

**Add or Update Recipe Ingredient for a Product starting**
* **Endpoint**: `POST /api/v1/Product/{productId}/recipe/new`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
```json
{
    "data": {
        "values": {
            "ingredientId": "ingredient_uuid",
            "quantityRequired": 200.0
        }
    }
}
```
* **Response (201 Created)**:
```json
{
    "status": true,
    "message": "Recipe ingredient added successfully.",
    "data": {
        "values": {
            "id": "recipe_uuid",
            "productId": "product_uuid",
            "ingredientId": "ingredient_uuid",
            "quantityRequired": 200.0,
            "ingredient": {
                "values": {
                    "id": "ingredient_uuid",
                    "organizationId": "org_uuid",
                    "vendorId": "vendor_uuid",
                    "name": "Sugar",
                    "unit": "g",
                    "minimumStockLevel": 500.0,
                    "currentStock": 2000.0,
                    "createdAt": "2026-06-11T21:31:35.000000Z",
                    "updatedAt": "2026-06-11T21:31:35.000000Z"
                }
            }
        }
    }
}
```

* **Additional Request Body Examples**:

**Example: Adding Flour Requirement**
*(e.g., Recipe requires 1.5 kg of flour per batch)*
```json
{
    "data": {
        "values": {
            "ingredientId": "{{flour_ingredient_uuid}}",
            "quantityRequired": 1.5
        }
    }
}
```

**Example: Adding Butter Requirement**
*(e.g., Recipe requires 0.5 kg of butter per batch)*
```json
{
    "data": {
        "values": {
            "ingredientId": "{{butter_ingredient_uuid}}",
            "quantityRequired": 0.5
        }
    }
}
```

**Example: Adding Yeast Requirement**
*(e.g., Recipe requires 15 grams of yeast per batch)*
```json
{
    "data": {
        "values": {
            "ingredientId": "{{yeast_ingredient_uuid}}",
            "quantityRequired": 15
        }
    }
}
```

**Example: Adding Chocolate Chips Requirement**
*(e.g., Recipe requires 2 kg of chocolate chips per batch)*
```json
{
    "data": {
        "values": {
            "ingredientId": "{{chocolate_chips_ingredient_uuid}}",
            "quantityRequired": 2
        }
    }
}
```

**Add or Update Recipe Ingredient for a Product ending**


### 8.3 Get Recipe Ingredient by ID

**Get Recipe Ingredient by ID starting**
* **Endpoint**: `GET /api/v1/Product/{productId}/recipe/{ingredientId}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Success",
    "data": {
        "fields": [
            {
                "fieldname": "id",
                "fieldlabel": "ID"
            },
            {
                "fieldname": "productId",
                "fieldlabel": "Product ID"
            },
            {
                "fieldname": "ingredientId",
                "fieldlabel": "Ingredient ID"
            },
            {
                "fieldname": "quantityRequired",
                "fieldlabel": "Quantity Required"
            }
        ],
        "values": {
            "id": "recipe_uuid",
            "productId": "product_uuid",
            "ingredientId": "ingredient_uuid",
            "quantityRequired": 200.0,
            "ingredient": {
                "id": "ingredient_uuid",
                "organizationId": "org_uuid",
                "vendorId": "vendor_uuid",
                "name": "Sugar",
                "unit": "g",
                "minimumStockLevel": 500.0,
                "currentStock": 2000.0,
                "createdAt": "2026-06-11T21:31:35.000000Z",
                "updatedAt": "2026-06-11T21:31:35.000000Z"
            }
        }
    }
}
```

**Get Recipe Ingredient by ID ending**


### 8.4 Remove Ingredient from a Product's Recipe

**Remove Ingredient from a Product's Recipe starting**
* **Endpoint**: `DELETE /api/v1/Product/{productId}/recipe/{ingredientId}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Recipe ingredient successfully removed."
}
```

**Remove Ingredient from a Product's Recipe ending**


---

## 9. Saved Filters

### 9.1 Create a Saved Filter

**Create a Saved Filter starting**
* **Endpoint**: `POST /api/v1/filters/new`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
*(Note: `module` accepts both PascalCase and lowercase: `User`/`users`, `Vendor`/`vendors`, `Ingredient`/`ingredients`, `InventoryTransaction`/`inventory_transactions`, `Product`/`products`)*

*(Note: `rules.conditions` is required. Each condition needs `field`, `operator`, and `value`. Allowed operators: `=`, `!=`, `>`, `<`, `>=`, `<=`, `like`, `LIKE`, `in`, `IN`)*

**Example 1: Filter Users by role (single condition)**
```json
{
    "data": {
        "values": {
            "name": "Admin Users Only",
            "module": "User",
            "isPublic": false,
            "rules": {
                "logical_operator": "AND",
                "conditions": [
                    {
                        "field": "role",
                        "operator": "=",
                        "value": "admin"
                    }
                ]
            }
        }
    }
}
```

**Example 2: Filter Users by role AND name search (multiple conditions)**
```json
{
    "data": {
        "values": {
            "name": "Admin Johns",
            "module": "User",
            "isPublic": false,
            "rules": {
                "logical_operator": "AND",
                "conditions": [
                    {
                        "field": "role",
                        "operator": "=",
                        "value": "admin"
                    },
                    {
                        "field": "firstName",
                        "operator": "like",
                        "value": "%john%"
                    }
                ]
            }
        }
    }
}
```

**Example 3: Filter Products by price range (OR logic)**
```json
{
    "data": {
        "values": {
            "name": "Cheap or Premium Products",
            "module": "Product",
            "isPublic": true,
            "rules": {
                "logical_operator": "OR",
                "conditions": [
                    {
                        "field": "price",
                        "operator": "<",
                        "value": 50
                    },
                    {
                        "field": "price",
                        "operator": ">",
                        "value": 500
                    }
                ]
            }
        }
    }
}
```

**Example 4: Filter Ingredients by vendor (single condition)**
```json
{
    "data": {
        "values": {
            "name": "Sugar Supplier Items",
            "module": "Ingredient",
            "isPublic": false,
            "rules": {
                "conditions": [
                    {
                        "field": "vendorId",
                        "operator": "=",
                        "value": "{{vendor_uuid}}"
                    }
                ]
            }
        }
    }
}
```

* **Response (201 Created)**:
```json
{
    "status": true,
    "message": "Success",
    "data": {
        "values": {
            "id": "new_filter_uuid",
            "organizationId": "{{org_uuid}}",
            "userId": "user_uuid",
            "name": "Admin Users Only",
            "module": "users",
            "rules": {
                "logical_operator": "AND",
                "conditions": [
                    {
                        "field": "role",
                        "operator": "=",
                        "value": "admin"
                    }
                ]
            },
            "isPublic": false,
            "createdAt": "2026-06-13T17:31:35.000000Z",
            "updatedAt": "2026-06-13T17:31:35.000000Z"
        }
    }
}
```

**Create a Saved Filter ending**


### 9.2 Allowed Fields per Module

**Allowed Fields per Module starting**
| Module | Allowed Fields (camelCase or snake_case) |
|--------|------------------------------------------|
| `User` / `users` | `firstName`, `lastName`, `email`, `role`, `createdAt` |
| `Vendor` / `vendors` | `name`, `contactPerson`, `email`, `phone`, `createdAt` |
| `Ingredient` / `ingredients` | `name`, `unit`, `minimumStockLevel`, `currentStock`, `vendorId`, `createdAt` |
| `InventoryTransaction` / `inventory_transactions` | `type`, `quantity`, `ingredientId`, `createdAt` |
| `Product` / `products` | `name`, `productNumber`, `price`, `unit`, `shelfLifeDays`, `currentStock`, `createdAt` |

**Allowed Fields per Module ending**


### 9.3 List Saved Filters

**List Saved Filters starting**
* **Endpoint**: `GET /api/v1/filters`
* **Headers**: `Authorization: Bearer {token}`
* **Optional Filters**:
  * Filter by module: `GET /api/v1/filters?module=User`
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Success",
    "data": [
        {
            "values": {
                "id": "filter_uuid",
                "organizationId": "org_uuid",
                "userId": "user_uuid",
                "name": "Admin Users Only",
                "module": "users",
                "rules": {
                    "logical_operator": "AND",
                    "conditions": [
                        {
                            "field": "role",
                            "operator": "=",
                            "value": "admin"
                        }
                    ]
                },
                "isPublic": false,
                "createdAt": "2026-06-13T17:31:35.000000Z",
                "updatedAt": "2026-06-13T17:31:35.000000Z"
            }
        }
    ]
}
```

**List Saved Filters ending**


### 9.4 Delete a Saved Filter

**Delete a Saved Filter starting**
* **Endpoint**: `DELETE /api/v1/filters/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "message": "Saved filter successfully deleted."
}
```

**Delete a Saved Filter ending**


### 9.5 Applying Saved Filters on Module Listings

**Applying Saved Filters on Module Listings starting**
For any list endpoint, append `?savedFilterId={filter_uuid}` to apply a previously saved filter:
* `GET /api/v1/settings/User?savedFilterId={filter_uuid}`
* `GET /api/v1/Vendor?savedFilterId={filter_uuid}`
* `GET /api/v1/Ingredient?savedFilterId={filter_uuid}`
* `GET /api/v1/InventoryTransaction?savedFilterId={filter_uuid}`
* `GET /api/v1/Product?savedFilterId={filter_uuid}`

**Applying Saved Filters on Module Listings ending**


### 9.6 Default Filters

**Default Filters starting**
When the app is first installed and migrations run, a default **"All"** filter is automatically created for each module (`users`, `vendors`, `ingredients`, `inventory_transactions`, `products`). These default filters:
* Have `is_default: true`
* Are global (not organization-specific)
* Include all module fields in `header_details`
* Cannot be deleted

**Default Filters ending**


### 9.7 Create Filter with Custom Header Details

**Create Filter with Custom Header Details starting**
When creating a saved filter, you can optionally pass `headerDetails` to specify which columns should be visible in the list view for that filter. If not provided, all module fields are used as default.

```json
{
    "data": {
        "values": {
            "name": "Admin Users - Name Only",
            "module": "User",
            "isPublic": false,
            "rules": {
                "logical_operator": "AND",
                "conditions": [
                    {
                        "field": "role",
                        "operator": "=",
                        "value": "admin"
                    }
                ]
            },
            "headerDetails": [
                { "fieldname": "id", "fieldlabel": "ID" },
                { "fieldname": "firstName", "fieldlabel": "First Name" },
                { "fieldname": "lastName", "fieldlabel": "Last Name" },
                { "fieldname": "email", "fieldlabel": "Email" }
            ]
        }
    }
}
```

**Create Filter with Custom Header Details ending**


---

## 10. Headers (Filter Field Definitions)

The Headers API returns the column/field definitions for a filter. It tells the frontend which columns to display in a list view.

### 10.1 Get Headers by Module (Default Filter)

**Get Headers by Module (Default Filter) starting**
* **Endpoint**: `GET /api/v1/{module_name}/headers`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Returns the default "All" filter's field definitions for the given module.

**Example**: `GET /api/v1/User/headers`

* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Success",
    "data": {
        "filter_id": "fa47fe48-a6b3-49db-9803-9d90755d5e88",
        "is_default": true,
        "fields": [
            { "fieldname": "id", "fieldlabel": "ID" },
            { "fieldname": "firstName", "fieldlabel": "First Name" },
            { "fieldname": "lastName", "fieldlabel": "Last Name" },
            { "fieldname": "email", "fieldlabel": "Email" },
            { "fieldname": "phone", "fieldlabel": "Phone" },
            { "fieldname": "role", "fieldlabel": "Role" },
            { "fieldname": "organizationId", "fieldlabel": "Organization ID" },
            { "fieldname": "createdAt", "fieldlabel": "Created At" }
        ]
    }
}
```

**Example**: `GET /api/v1/Product/headers`

* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Success",
    "data": {
        "filter_id": "849e4b64-b23a-4638-bd6e-afea74216ff9",
        "is_default": true,
        "fields": [
            { "fieldname": "id", "fieldlabel": "ID" },
            { "fieldname": "productNumber", "fieldlabel": "Product Number" },
            { "fieldname": "name", "fieldlabel": "Name" },
            { "fieldname": "description", "fieldlabel": "Description" },
            { "fieldname": "price", "fieldlabel": "Price" },
            { "fieldname": "unit", "fieldlabel": "Unit" },
            { "fieldname": "shelfLifeDays", "fieldlabel": "Shelf Life Days" },
            { "fieldname": "currentStock", "fieldlabel": "Current Stock" },
            { "fieldname": "createdAt", "fieldlabel": "Created At" },
            { "fieldname": "updatedAt", "fieldlabel": "Updated At" }
        ]
    }
}
```

**Get Headers by Module (Default Filter) ending**


### 10.2 Get Headers by Filter ID

**Get Headers by Filter ID starting**
* **Endpoint**: `GET /api/v1/{module_name}/headers/{filterId}`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Returns the field definitions stored in the given filter's `header_details`. If that filter has custom `headerDetails`, only those fields are returned (matched against the module's full field list).

**Example**: `GET /api/v1/User/headers/a3ae37e8-fc06-443f-a7fa-7c5fe4f6c62f`

* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Success",
    "data": {
        "filter_id": "a3ae37e8-fc06-443f-a7fa-7c5fe4f6c62f",
        "is_default": false,
        "fields": [
            { "fieldname": "id", "fieldlabel": "ID" },
            { "fieldname": "firstName", "fieldlabel": "First Name" },
            { "fieldname": "lastName", "fieldlabel": "Last Name" },
            { "fieldname": "email", "fieldlabel": "Email" }
        ]
    }
}
```

**Get Headers by Filter ID ending**


---

## 11. Global Search (Relation Picklists)

The Global Search API is used to populate relational picklists (dropdowns) across the application. When a user searches within a relational field (e.g., `vendorId`), this endpoint returns the matching records from the target module, along with the field definitions needed to display them.

### 11.1 Search by Field Name

**Search by Field Name starting**
* **Endpoint**: `GET /api/v1/search/{fieldname}?value={search_string}`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Returns matching records for a specific relation field. Allowed fields include `vendorId`, `userId`, `ingredientId`, `productId`, and `organizationId`.

**Example**: `GET /api/v1/search/vendorId?value=Gorf`

* **Response (200 OK)**:
```json
{
  "status": true,
  "message": "Success",
  "data": {
    "results": {
      "Vendor": {
        "fields": [
          {
            "fieldname": "id",
            "fieldlabel": "ID"
          },
          {
            "fieldname": "name",
            "fieldlabel": "Name"
          },
          {
            "fieldname": "contactPerson",
            "fieldlabel": "Contact Person"
          },
          {
            "fieldname": "phone",
            "fieldlabel": "Phone"
          },
          {
            "fieldname": "email",
            "fieldlabel": "Email"
          },
          {
            "fieldname": "createdAt",
            "fieldlabel": "Created At"
          }
        ],
        "values": [
          {
            "id": "a85bb0bc-260e-4970-b758-1b423e8e0332",
            "label": "Gorf Supplies",
            "search_text": "Gorf Supplies,Tammie Simon"
          }
        ]
      }
    }
  }
}
```

**Search by Field Name ending**

---

## 12. Branch Management (Phase 2)

### 12.1 Create Branch

**Branch Create starting**
* **Endpoint**: `POST /api/v1/Branch/new`
* **Request Body**:
  ```json
  {
      "data": {
          "values": {
              "name": "Main Warehouse",
              "type": "warehouse",
              "address": "123 Main St",
              "phone": "555-1234"
          }
      }
  }
  ```
* **Success Response (201 Created)**:
  ```json
  {
      "status": true,
      "message": "Branch created successfully.",
      "data": {
          "values": {
              "id": "e446549a-...",
              "organizationId": "...",
              "organizationId_label": "Demo",
              "name": "Main Warehouse",
              "type": "warehouse",
              "address": "123 Main St",
              "phone": "555-1234",
              "createdAt": "2026-06-18T10:00:00.000000Z",
              "updatedAt": "2026-06-18T10:00:00.000000Z"
          }
      }
  }
  ```
**Branch Create ending**

### 12.2 List Branches

**Branch List starting**
* **Endpoint**: `GET /api/v1/Branch`
* **Success Response (200 OK)**:
  ```json
  {
      "status": true,
      "message": "Success",
      "data": {
          "values": [
              {
                  "id": "e446549a-...",
                  "name": "Main Warehouse",
                  "type": "warehouse",
                  ...
              }
          ]
      }
  }
  ```
**Branch List ending**

### 12.3 Get Branch Details

**Branch Detail starting**
* **Endpoint**: `GET /api/v1/Branch/{id}`
* **Success Response (200 OK)**:
  ```json
  {
      "status": true,
      "message": "Success",
      "data": {
          "values": {
              "id": "e446549a-...",
              "name": "Main Warehouse",
              "type": "warehouse",
              ...
          }
      }
  }
  ```
**Branch Detail ending**

### 12.4 Update Branch

**Branch Update starting**
* **Endpoint**: `POST /api/v1/Branch/{id}`
* **Request Body**: Same as Create.
* **Success Response (200 OK)**: Same structure as Create.
**Branch Update ending**

### 12.5 Delete Branch

**Branch Delete starting**
* **Endpoint**: `DELETE /api/v1/Branch/{id}`
* **Success Response (200 OK)**:
  ```json
  {
      "status": true,
      "message": "Branch successfully deleted.",
      "data": null
  }
  ```
**Branch Delete ending**

---

## 13. Production Batch Management

### 13.1 List Production Batches

**List Production Batches starting**
* **Endpoint**: `GET /api/v1/ProductionBatch`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Success",
    "data": {
        "values": [
            {
                "id": "uuid",
                "organizationId": "uuid",
                "batchNumber": "BATCH-20260620-001",
                "productId": "uuid",
                "quantityProduced": 200.0,
                "productionDate": "2026-06-20",
                "expiryDate": "2026-06-25",
                "status": "completed",
                "notes": "Morning run",
                "createdAt": "2026-06-20 10:00:00"
            }
        ]
    }
}
```
**List Production Batches ending**

### 13.2 Create Production Batch

**Create Production Batch starting**
* **Endpoint**: `POST /api/v1/ProductionBatch/new`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
```json
{
    "data": {
        "values": {
            "productId": "{{product_uuid}}",
            "quantityProduced": 200,
            "productionDate": "2026-06-20",
            "notes": "Morning run"
        }
    }
}
```
* **Response (201 Created)**:
```json
{
    "status": true,
    "message": "Production batch logged successfully.",
    "data": {
        "values": {
            "id": "new_batch_uuid",
            "organizationId": "{{org_uuid}}",
            "batchNumber": "BATCH-20260620-001",
            "productId": "{{product_uuid}}",
            "quantityProduced": 200.0,
            "productionDate": "2026-06-20",
            "expiryDate": "2026-06-25",
            "status": "completed",
            "notes": "Morning run",
            "createdAt": "2026-06-20 10:00:00"
        }
    }
}
```
**Create Production Batch ending**

### 13.3 Get Production Batch by ID

**Get Production Batch by ID starting**
* **Endpoint**: `GET /api/v1/ProductionBatch/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**: Returns the single batch object exactly as seen in the Create/List response, along with the `fields` array.
**Get Production Batch by ID ending**

### 13.4 Delete Production Batch

**Delete Production Batch starting**
* **Endpoint**: `DELETE /api/v1/ProductionBatch/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Production Batch successfully deleted."
}
```
* **Note**: Deleting a batch simply removes the record; it does *not* automatically reverse the ingredient consumption or finished goods additions in this phase.
**Delete Production Batch ending**

### 13.5 Update Production Batch

**Update Production Batch starting**
* **Endpoint**: `POST /api/v1/ProductionBatch/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
```json
{
    "data": {
        "values": {
            "quantityProduced": 250,
            "productionDate": "2026-06-20",
            "status": "completed",
            "notes": "Morning run - updated quantity"
        }
    }
}
```
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Production batch updated successfully.",
    "data": {
        "values": {
            "id": "batch_uuid",
            "organizationId": "{{org_uuid}}",
            "batchNumber": "BATCH-20260620-001",
            "productId": "{{product_uuid}}",
            "quantityProduced": 250.0,
            "productionDate": "2026-06-20",
            "expiryDate": "2026-06-25",
            "status": "completed",
            "notes": "Morning run - updated quantity",
            "createdAt": "2026-06-20 10:00:00"
        }
    }
}
```
* **Note**: Updating the `quantityProduced` will automatically calculate the difference and create the necessary `InventoryTransaction` to add/deduct the remaining ingredients and update the `Product`'s `current_stock`.
**Update Production Batch ending**

## 14. Branch Stock Module

### 14.1 List Branch Stocks

**List Branch Stocks starting**
* **Endpoint**: `GET /api/v1/BranchStock`
* **Headers**: `Authorization: Bearer {token}`
* **Query Parameters (Optional)**: `?branchId={{branch_uuid}}`, `?productId={{product_uuid}}`
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Success",
    "data": {
        "fields": [...],
        "values": [
            {
                "id": "branch_stock_uuid",
                "organizationId": "{{org_uuid}}",
                "branchId": "{{branch_uuid}}",
                "branchId_label": "Branch 1",
                "productId": "{{product_uuid}}",
                "productId_label": "Egg Puff",
                "currentStock": 50.0,
                "updatedAt": "2026-06-21 10:00:00"
            }
        ]
    }
}
```
**List Branch Stocks ending**

## 15. Branch Transfer Module

### 15.1 Create Branch Transfer (Dispatch)

**Create Branch Transfer starting**
* **Endpoint**: `POST /api/v1/BranchTransfer/new`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
```json
{
    "data": {
        "values": {
            "branchId": "{{branch_uuid}}",
            "productId": "{{product_uuid}}",
            "quantity": 50,
            "transferDate": "2026-06-21",
            "notes": "Morning dispatch"
        }
    }
}
```
* **Response (201 Created)**:
```json
{
    "status": true,
    "message": "Transfer logged successfully.",
    "data": {
        "values": {
            "id": "transfer_uuid",
            "organizationId": "{{org_uuid}}",
            "branchId": "{{branch_uuid}}",
            "branchId_label": "Branch 1",
            "productId": "{{product_uuid}}",
            "productId_label": "Egg Puff",
            "transferNumber": "TRN-20260621-001",
            "quantity": 50.0,
            "transferDate": "2026-06-21",
            "status": "completed",
            "notes": "Morning dispatch",
            "createdAt": "2026-06-21 10:00:00"
        }
    }
}
```
**Create Branch Transfer ending**

### 15.2 Update Branch Transfer

**Update Branch Transfer starting**
* **Endpoint**: `POST /api/v1/BranchTransfer/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
```json
{
    "data": {
        "values": {
            "transferDate": "2026-06-21",
            "notes": "Updated note"
        }
    }
}
```
* **Note**: Only non-inventory impacting fields like date and notes can be updated.
**Update Branch Transfer ending**

## 16. Reports Module

### 16.1 Expiring Batches Report

**Expiring Batches starting**
* **Endpoint**: `GET /api/v1/Reports/ExpiringBatches`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Expiry report fetched successfully.",
    "data": {
        "summary": {
            "expiredCount": 1,
            "expiringSoonCount": 2,
            "healthyCount": 5
        },
        "expired": [
            {
                "id": "batch_uuid_1",
                "batchNumber": "BATCH-20260620-001",
                "productName": "Egg Puff",
                "tier": "tier_1",
                "quantityProduced": 200,
                "productionDate": "2026-06-20",
                "expiryTimestamp": "2026-06-20 22:00:00"
            }
        ],
        "expiringSoon": [...],
        "healthy": [...]
    }
}
```
**Expiring Batches ending**

## 17. Branch Sales & Returns Module

### 17.1 Create Branch Daily Report

**Create Branch Daily Report starting**
* **Endpoint**: `POST /api/v1/BranchDailyReport/new`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
```json
{
    "data": {
        "values": {
            "branchId": "{{branch_uuid}}",
            "reportDate": "2026-06-21",
            "notes": "End of day report",
            "items": [
                {
                    "productId": "{{product_uuid}}",
                    "quantitySold": 40,
                    "quantityReturned": 10
                }
            ]
        }
    }
}
```
* **Response (201 Created)**:
```json
{
    "status": true,
    "message": "Branch Daily Report submitted successfully.",
    "data": {
        "values": {
            "id": "report_uuid",
            "organizationId": "{{org_uuid}}",
            "branchId": "{{branch_uuid}}",
            "branchId_label": "Branch 1",
            "reportDate": "2026-06-21",
            "totalRevenue": 400.0,
            "totalWasteAmount": 100.0,
            "status": "submitted",
            "notes": "End of day report",
            "createdAt": "2026-06-21 18:00:00",
            "items": [
                {
                    "id": "item_uuid",
                    "productId": "{{product_uuid}}",
                    "productId_label": "Egg Puff",
                    "quantitySold": 40.0,
                    "quantityReturned": 10.0,
                    "unitPrice": 10.0,
                    "subtotalRevenue": 400.0,
                    "subtotalWaste": 100.0
                }
            ]
        }
    }
}
```
**Create Branch Daily Report ending**

### 17.2 List Branch Daily Reports

**List Branch Daily Reports starting**
* **Endpoint**: `GET /api/v1/BranchDailyReport`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**: Standard paginated list response with fields and values.
**List Branch Daily Reports ending**

## 18. Dashboard Module

### 18.1 Dashboard Summary

**Dashboard Summary starting**
* **Endpoint**: `GET /api/v1/Dashboard/Summary`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Dashboard summary fetched successfully.",
    "data": {
        "kpis": {
            "salesToday": 400.0,
            "wasteToday": 100.0,
            "productionBatchesToday": 2
        },
        "salesTrend7Days": [
            {
                "date": "2026-06-15",
                "revenue": 0
            },
            {
                "date": "2026-06-21",
                "revenue": 400.0
            }
        ],
        "topProducts30Days": [
            {
                "name": "Egg Puff",
                "totalSold": 40.0
            }
        ]
    }
}
```
**Dashboard Summary ending**

## 19. Global Endpoints

### 19.1 Inline Edit

**Inline Edit starting**
* **Endpoint**: `PATCH /api/v1/{module}/{id}/inline-edit`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Allows quick updating of a single field on a given record. `{module}` can be any valid module name (e.g., `User`, `Product`, `Vendor`, `Branch`). The `field` must be passed in camelCase (e.g., `lastName`) and it will automatically be mapped to the database column (`last_name`). For security, only fillable fields are allowed.
* **Request Body**:
```json
{
    "field": "lastName",
    "value": "Im suresh"
}
```
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Successfully updated 'lastName'.",
    "data": {
        "id": "uuid-of-record",
        "field": "lastName",
        "value": "Im suresh"
    }
}
```
**Inline Edit ending**

### 19.2 Audit Log

**Audit Log starting**
* **Endpoint**: `GET /api/v1/{module}/{id}/audit-log`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Fetches the chronological history of all changes (create, update, delete) made to a specific record. `{module}` can be any valid module name (e.g., `User`, `Product`, `Branch`). 
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Audit logs fetched successfully.",
    "data": [
        {
            "id": "audit_log_uuid",
            "event": "updated",
            "label": "Record updated",
            "action_by": {
                "id": "de341d3f-230a-4f6f-b670-20d8bf6777c9",
                "name": "Cool Suresh",
                "label": "Cool Suresh"
            },
            "old_values": {
                "price": 100.00
            },
            "new_values": {
                "price": 150.50
            },
            "timestamp": "2026-06-23 15:30:00"
        },
        {
            "id": "audit_log_uuid_2",
            "event": "created",
            "label": "Record created",
            "action_by": {
                "id": null,
                "name": "System",
                "label": "System"
            },
            "old_values": null,
            "new_values": {
                "name": "Egg Puff",
                "price": 100.00
            },
            "timestamp": "2026-06-21 09:00:00"
        }
    ]
}
```
**Audit Log ending**

---

## 20. Billing Module

### 20.1 Get POS Categories
* **Endpoint**: `GET /api/v1/Billing/pos-products/category`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Returns the list of available product categories for POS tabs, with "All" always at the beginning.
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "POS Categories retrieved successfully",
    "data": [
        "All",
        "Bread",
        "Sweet",
        "Cake",
        "Snack",
        "Beverage",
        "Other"
    ]
}
```

### 20.2 Get POS Products
* **Endpoint**: `GET /api/v1/Billing/pos-products?category=All`
* **Headers**: `Authorization: Bearer {token}`
* **Query Parameters**:
  * `category` (optional, string) - Filter by category name. Pass "All" or omit it to get all products.
  * `per_page` (optional, integer) - Pagination limit, default 20.
* **Description**: Returns a paginated list of active products for the POS screen, filtered by the provided category tab.
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "POS Products retrieved successfully",
    "data": {
        "fields": [
            {
                "fieldname": "id",
                "fieldlabel": "ID",
                "fieldtype": "text",
                "displaytype": 2
            },
            {
                "fieldname": "name",
                "fieldlabel": "Name",
                "fieldtype": "text",
                "displaytype": 1
            },
            {
                "fieldname": "price",
                "fieldlabel": "Price",
                "fieldtype": "currency",
                "displaytype": 1
            }
        ],
        "list": [
            {
                "id": "uuid-2",
                "name": "Halwa",
                "price": 200,
                "unit": "Gram",
                "category": "Sweet"
            }
        ],
        "meta": {
            "current_page": 1,
            "last_page": 1,
            "per_page": 20,
            "total": 1
        },
        "links": {
            "first": "http://localhost/api/v1/Billing/pos-products?page=1",
            "last": "http://localhost/api/v1/Billing/pos-products?page=1",
            "prev": null,
            "next": null
        }
    }
}
```

### 20.3 Create Form Fields
* **Endpoint**: `GET /api/v1/Billing/new`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Returns the form fields configuration for creating a new bill.

### 20.4 Get List Headers
* **Endpoint**: `GET /api/v1/Billing/headers`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Returns the table headers for the billing list view.

### 20.5 List Bills (Billing History)
* **Endpoint**: `GET /api/v1/Billing`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Fetches a paginated list of all past bills (Billing History).

### 20.6 View Specific Bill
* **Endpoint**: `GET /api/v1/Billing/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Fetches the details of a specific bill, including its line items.

### 20.7 Create New Bill (Postman Example)
* **Endpoint**: `POST /api/v1/Billing/new`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Processes a new transaction and creates a bill.
* **Body**:
```json
{
    "data": {
        "values": {
            "branchId": "019ec048-8bcc-7039-848a-a58261ad3cbe",
            "customerName": "John Doe",
            "customerPhone": "1234567890",
            "customerEmail": "john@example.com",
            "discountAmount": 5.00,
            "taxAmount": 2.50,
            "paymentMethod": "Cash",
            "paymentStatus": "Paid"
        },
        "relatedRecords": {
            "items": [
                {
                    "productId": "019eb5c4-8bcc-7039-848a-a58261ad3cbe",
                    "quantity": 2,
                    "unitPrice": 50.00
                },
                {
                    "productId": "019eb5c4-8bcc-7039-848a-a58261ad3cbe",
                    "quantity": 1,
                    "unitPrice": 100.00
                }
            ]
        }
    }
}
```
* **Response (200 OK)**:
```json
{
    "status": true,
    "message": "Bill created successfully",
    "data": {
        "id": "uuid",
        "branchId": "019ec048-8bcc-7039-848a-a58261ad3cbe",
        "branchId_label": "Main Branch",
        "billNumber": "BILL-20260626-ABCD",
        "customerName": "John Doe",
        "customerPhone": "1234567890",
        "customerEmail": "john@example.com",
        "subTotal": 200.00,
        "discountAmount": 5.00,
        "taxAmount": 2.50,
        "grandTotal": 197.50,
        "paymentMethod": "Cash",
        "paymentStatus": "Paid",
        "billingDate": "2026-06-26 12:00:00",
        "items": [
            {
                "id": "uuid",
                "productId": "019eb5c4-8bcc-7039-848a-a58261ad3cbe",
                "productId_label": "Product A",
                "quantity": 2.00,
                "unitPrice": 50.00,
                "totalPrice": 100.00
            },
            {
                "id": "uuid",
                "productId": "019eb5c4-8bcc-7039-848a-a58261ad3cbe",
                "productId_label": "Product B",
                "quantity": 1.00,
                "unitPrice": 100.00,
                "totalPrice": 100.00
            }
        ],
        "createdAt": "2026-06-26 12:00:00"
    }
}
```

### 20.8 Update Bill
* **Endpoint**: `PUT /api/v1/Billing/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Updates an existing bill. This is useful for changing the `paymentStatus` (e.g. from Pending to Paid) or modifying the `items` array to add/remove products.
* **Body**:
```json
{
    "data": {
        "values": {
            "paymentStatus": "Paid"
        },
        "relatedRecords": {
            "items": [
                {
                    "id": "existing-billing-item-uuid",
                    "productId": "019eb5c4-8bcc-7039-848a-a58261ad3cbe",
                    "quantity": 2,
                    "unitPrice": 50.00
                },
                {
                    "productId": "new-product-uuid",
                    "quantity": 1,
                    "unitPrice": 100.00
                }
            ]
        }
    }
}
```
* **Note**: If `relatedRecords.items` is provided, the backend will sync the items (add new ones without `id`, update existing ones with `id`, and delete any old items not included in the array). The `grandTotal` will automatically recalculate.
* **Response (200 OK)**: Returns the updated Bill object (same structure as `POST /api/v1/Billing/new`).

---

## 21. Settings: Dynamic Field Management

### 21.1 List Fields
* **Endpoint**: `GET /api/v1/settings/fields?module={module}`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Lists all standard and custom fields for a specific module (e.g., `Product`, `Ingredient`, `Billing`).
* **Response (200 OK)**:
```json
{
    "success": true,
    "data": [
        {
            "id": "field_uuid",
            "fieldname": "productNumber",
            "fieldlabel": "Product Number",
            "mandatory": false,
            "fieldtype": "text",
            "displaytype": 1,
            "is_custom_field": false
        }
    ]
}
```

### 21.2 Create Custom Field
* **Endpoint**: `POST /api/v1/settings/fields/new`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Creates a new custom field dynamically and prepares the database table if needed. Note: Valid field types include `text`, `textarea`, `number`, `email`, `date`, `datetime`, `picklist`, `multiselect`, `checkbox`.
* **Request Body**:
```json
{
    "data": {
        "fieldlabel": "Expiry Alert Days",
        "fieldtype": "number",
        "modulename": "Product",
        "mandatory": 0,
        "options": []
    }
}
```
* **Response (200 OK)**:
```json
{
    "success": true,
    "data": {
        "id": "new_field_uuid",
        "modulename": "Product",
        "fieldname": "expiry_alert_days",
        "fieldlabel": "Expiry Alert Days",
        "fieldtype": "number",
        "tablename": "lproduct_custom_values",
        "mandatory": 0,
        "apifieldname": "expiryAlertDays",
        "displaytype": 1,
        "is_custom_field": 1
    }
}
```

### 21.3 Get Field Details
* **Endpoint**: `GET /api/v1/settings/fields/{module}/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Retrieve details about a specific field, including its picklist options if applicable.
* **Response (200 OK)**:
```json
{
    "success": true,
    "data": {
        "id": "field_uuid",
        "fieldlabel": "Category",
        "fieldtype": "picklist",
        "modulename": "Product",
        "mandatory": 0,
        "options": [
            { "label": "Bread", "value": "bread" },
            { "label": "Cake", "value": "cake" }
        ]
    }
}
```

### 21.4 Update Field Label
* **Endpoint**: `POST /api/v1/settings/fields/update-label`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Updates the display label or mandatory status of a standard or custom field.
* **Request Body**:
```json
{
    "data": {
        "id": "field_uuid",
        "fieldlabel": "Updated Label",
        "mandatory": 1
    }
}
```
* **Response (200 OK)**:
```json
{
    "success": true,
    "data": {
        "message": "Field label updated successfully",
        "field_id": "field_uuid"
    }
}
```

### 21.5 Delete Custom Field
* **Endpoint**: `DELETE /api/v1/settings/fields/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Description**: Soft-deletes a custom field and cleans up related values. Note: Only custom fields (`is_custom_field` = 1) can be deleted.
* **Response (200 OK)**:
```json
{
    "success": true,
    "data": {
        "message": "Custom field deleted successfully",
        "field_id": "field_uuid"
    }
}
```
