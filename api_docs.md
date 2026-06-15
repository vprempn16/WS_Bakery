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

## 4. Ingredient Management

### 4.1 List Ingredients
* **Endpoint**: `GET /api/v1/ingredients`
* **Headers**: `Authorization: Bearer {token}`
* **Optional Filters**:
  * Search by name: `GET /api/v1/ingredients?search={keyword}`
  * Filter by Vendor: `GET /api/v1/ingredients?vendorId={vendor_uuid}`
  * Filter by Stock Status: `GET /api/v1/ingredients?stockStatus={low|in_stock}`
  * Combined: `GET /api/v1/ingredients?search={keyword}&vendorId={vendor_uuid}&stockStatus={low}`
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

### 4.2 Create Ingredient
* **Endpoint**: `POST /api/v1/ingredients/new`
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

### 4.3 Get Ingredient by ID
* **Endpoint**: `GET /api/v1/ingredients/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**: Same structure as single element in list response.

### 4.4 Update Ingredient
* **Endpoint**: `PUT /api/v1/ingredients/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**: Same as Create but fields to update.
* **Response (200 OK)**: Updated ingredient object.

### 4.5 Delete Ingredient
* **Endpoint**: `DELETE /api/v1/ingredients/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{ "message": "Ingredient successfully deleted." }
```

### 4.6 Low Stock Endpoint
* **Endpoint**: `GET /api/v1/ingredients/low-stock`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**: List of ingredients where `current_stock` < `minimum_stock_level`.

---

## 5. Vendor Management

### 5.1 List Vendors
* **Endpoint**: `GET /api/v1/vendors`
* **Headers**: `Authorization: Bearer {token}`
* **Optional Filters**:
  * Search by name, contact person, email, or phone: `GET /api/v1/vendors?search={keyword}`
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

### 5.2 Create Vendor
* **Endpoint**: `POST /api/v1/vendors/new`
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

### 5.3 Get Vendor by ID
* **Endpoint**: `GET /api/v1/vendors/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**: Same structure as single element in list response.

### 5.4 Update Vendor
* **Endpoint**: `PUT /api/v1/vendors/{id}`
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

### 5.5 Delete Vendor
* **Endpoint**: `DELETE /api/v1/vendors/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "message": "Vendor successfully deleted."
}
```

---

## 6. Inventory Transactions

### 6.1 List Inventory Transactions
* **Endpoint**: `GET /api/v1/inventory-transactions`
* **Headers**: `Authorization: Bearer {token}`
* **Optional Filters**:
  * Filter by Ingredient: `GET /api/v1/inventory-transactions?ingredientId={ingredient_uuid}`
  * Filter by Transaction Type: `GET /api/v1/inventory-transactions?type={in|out|waste|production}`
  * Date Range Filters: `GET /api/v1/inventory-transactions?startDate={YYYY-MM-DD}&endDate={YYYY-MM-DD}`
  * Combined: `GET /api/v1/inventory-transactions?ingredientId={ingredient_uuid}&type=waste&startDate=2026-06-01&endDate=2026-06-15`
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

### 6.2 Create Inventory Transaction
* **Endpoint**: `POST /api/v1/inventory-transactions/new`
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

### 6.3 Get Inventory Transaction by ID
* **Endpoint**: `GET /api/v1/inventory-transactions/{id}`
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

---

## 7. Product Management

### 7.1 List Products
* **Endpoint**: `GET /api/v1/products`
* **Headers**: `Authorization: Bearer {token}`
* **Optional Filters**:
  * Search by name or product number: `GET /api/v1/products?search={keyword_or_product_number}`
  * Filter by Unit: `GET /api/v1/products?unit={pcs|kg|g|l|ml|pkt}`
  * Filter by Stock Status: `GET /api/v1/products?stockStatus={out_of_stock|in_stock}`
  * Combined: `GET /api/v1/products?search=Bread&unit=pcs&stockStatus=in_stock`
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

### 7.2 Create Product
* **Endpoint**: `POST /api/v1/products/new`
* **Headers**: `Authorization: Bearer {token}`
* **Request Body**:
*(Note: `productNumber` is auto-generated on creation as `PROD1`, `PROD2`, etc. `unit` must be one of: `pcs`, `kg`, `g`, `l`, `ml`, `pkt`. Defaults to `pcs` if not provided)*
```json
{
    "data": {
        "values": {
            "organizationId": "{{org_uuid}}",
            "name": "Sweet Bread",
            "description": "Delicious baked sweet bread",
            "price": 50,
            "unit": "pcs",
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
            "unit": "pcs",
            "shelfLifeDays": 3,
            "currentStock": 0,
            "createdAt": "2026-06-11T21:31:35.000000Z",
            "updatedAt": "2026-06-11T21:31:35.000000Z"
        }
    }
}
```

### 7.3 Get Product by ID
* **Endpoint**: `GET /api/v1/products/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**: Same structure as single element in list response.

### 7.4 Update Product
* **Endpoint**: `PUT /api/v1/products/{id}`
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
            "unit": "pcs",
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
            "unit": "pcs",
            "shelfLifeDays": 4,
            "currentStock": 0,
            "createdAt": "2026-06-11T21:31:35.000000Z",
            "updatedAt": "2026-06-11T21:31:35.000000Z"
        }
    }
}
```

### 7.5 Delete Product
* **Endpoint**: `DELETE /api/v1/products/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "message": "Product successfully deleted."
}
```

---

## 8. Recipe Management

### 8.1 List Recipe Ingredients for a Product
* **Endpoint**: `GET /api/v1/products/{productId}/recipe`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
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

### 8.2 Add or Update Recipe Ingredient for a Product
* **Endpoint**: `POST /api/v1/products/{productId}/recipe/new`
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

### 8.3 Get Recipe Ingredient by ID
* **Endpoint**: `GET /api/v1/products/{productId}/recipe/{ingredientId}`
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

### 8.4 Remove Ingredient from a Product's Recipe
* **Endpoint**: `DELETE /api/v1/products/{productId}/recipe/{ingredientId}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
}
```

---

## 9. Saved Filters

### 9.1 Create a Saved Filter
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

### 9.2 Allowed Fields per Module
| Module | Allowed Fields (camelCase or snake_case) |
|--------|------------------------------------------|
| `User` / `users` | `firstName`, `lastName`, `email`, `role`, `createdAt` |
| `Vendor` / `vendors` | `name`, `contactPerson`, `email`, `phone`, `createdAt` |
| `Ingredient` / `ingredients` | `name`, `unit`, `minimumStockLevel`, `currentStock`, `vendorId`, `createdAt` |
| `InventoryTransaction` / `inventory_transactions` | `type`, `quantity`, `ingredientId`, `createdAt` |
| `Product` / `products` | `name`, `productNumber`, `price`, `unit`, `shelfLifeDays`, `currentStock`, `createdAt` |

### 9.3 List Saved Filters
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

### 9.4 Delete a Saved Filter
* **Endpoint**: `DELETE /api/v1/filters/{id}`
* **Headers**: `Authorization: Bearer {token}`
* **Response (200 OK)**:
```json
{
    "message": "Saved filter successfully deleted."
}
```

### 9.5 Applying Saved Filters on Module Listings
For any list endpoint, append `?savedFilterId={filter_uuid}` to apply a previously saved filter:
* `GET /api/v1/settings/User?savedFilterId={filter_uuid}`
* `GET /api/v1/vendors?savedFilterId={filter_uuid}`
* `GET /api/v1/ingredients?savedFilterId={filter_uuid}`
* `GET /api/v1/inventory-transactions?savedFilterId={filter_uuid}`
* `GET /api/v1/products?savedFilterId={filter_uuid}`

### 9.6 Default Filters
When the app is first installed and migrations run, a default **"All"** filter is automatically created for each module (`users`, `vendors`, `ingredients`, `inventory_transactions`, `products`). These default filters:
* Have `is_default: true`
* Are global (not organization-specific)
* Include all module fields in `header_details`
* Cannot be deleted

### 9.7 Create Filter with Custom Header Details
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

---

## 10. Headers (Filter Field Definitions)

The Headers API returns the column/field definitions for a filter. It tells the frontend which columns to display in a list view.

### 10.1 Get Headers by Module (Default Filter)
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

### 10.2 Get Headers by Filter ID
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
