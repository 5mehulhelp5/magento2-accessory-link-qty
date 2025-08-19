# InSession Accessory Link Qty

Magento 2 module: Adds a custom **product link type** `partlists`, including **quantity (qty)** and **position**.  
This allows you to link spare parts or accessories to a main product with the required quantity.

---

## Why would you need this module?

By default, Magento only supports related, upsell, cross-sell and grouped product links.  
This module adds a new link type **`partlists`** that can be used to model a *production bill of materials* (BOM).

**Example use cases:**
- You want to sell a **set** as a simple product with its own SKU and stock management,  
  but still link and display the required **components** (e.g. jug, glass, spoon).  
- You need a way to reflect an **ERP production bill of materials** inside Magento,  
  so that each component can be managed individually while the parent product is still sold as a simple SKU.  
- You want to show end customers which parts are included in a set, including required **quantities**.

---

## Features
- New product link type: `partlists`
- Quantity (`qty`) and position for each link
- Admin UI integration (tab in the product form Related Products, Up-Sells, Cross-Sells and Partlists )
- Import/Export support via CSV (`_partlists_` column)
- GraphQL support
- REST API support out of the box
- 
---

## Frontend Example (optional)

The module ships with a **sample `.phtml` template** for rendering linked `partlists` products.  
This is provided as a **demonstration only** and is **not intended for production use**.

- The sample block is defined in `view/frontend/layout/catalog_product_view.xml`.  
- By default, it is **commented out**.  
- If you want to use it, copy the block and template into your **custom theme** and adjust as needed.

**Recommendation:**  
Implement your own block or UI component in your theme that consumes the `partlists` data (via GraphQL, REST API, or the Partlists domain model) and renders it in your desired design.

---
## Installation

### Composer Installation
```bash
composer require in-session/module-accessory-link-qty
```

### Manual Installation
1. Create the directory:
   ```bash
   mkdir -p app/code/InSession/AccessoryLinkQty
   ```
2. Download the module code and place it in:
   ```
   app/code/InSession/AccessoryLinkQty
   ```

### Enable the module
```bash
bin/magento module:enable InSession_AccessoryLinkQty
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

---

## Import / Export

### CSV Import
Column name: `_partlists_`

Format per row:
```
SKU|QTY|POSITION
```

Multiple links are separated by commas:
```
sku,_partlists_
BASE-123,"MILK-JUG|2|10,LATTE-GLASS|0.2|20"
```

### CSV Export
The export automatically writes the `_partlists_` column in the same format.

---

## GraphQL Example

```graphql
query {
  products(filter: { sku: { eq: "BASE-123" } }) {
    items {
      sku
      partlists {
        items {
          qty
          position
          product {
            sku
            name
            small_image { url }
          }
        }
      }
    }
  }
}
```

---

## REST API

This module is fully compatible with Magento's REST API.  
You can read and write `partlists` links using the standard `/V1/products/:sku/links` endpoints.

### Read partlists
```http
GET /rest/V1/products/BASE-123/links
```

Example response excerpt:
```json
[
  {
    "sku": "BASE-123",
    "link_type": "partlists",
    "linked_product_sku": "MILK-JUG",
    "position": 10,
    "extension_attributes": {
      "qty": 2
    }
  },
  {
    "sku": "BASE-123",
    "link_type": "partlists",
    "linked_product_sku": "LATTE-GLASS",
    "position": 20,
    "extension_attributes": {
      "qty": 0.2
    }
  }
]
```

### Create or update partlists
```http
POST /rest/V1/products/BASE-123/links
```

Payload example:
```json
{
  "entity": {
    "sku": "BASE-123",
    "link_type": "partlists",
    "linked_product_sku": "MILK-JUG",
    "position": 10,
    "extension_attributes": {
      "qty": 2
    }
  }
}
```

---
