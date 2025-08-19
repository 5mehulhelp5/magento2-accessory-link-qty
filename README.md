# InSession Accessory Link Qty

Magento 2 module: Adds a custom **product link type** `partlists`, including **quantity (qty)** and **position**.  
This allows you to link spare parts or accessories to a main product with the required quantity.

---

## Features
- New product link type: `partlists`
- Quantity (`qty`) and position for each link
- Admin UI integration (tab in the product form Related Products, Up-Sells, Cross-Sells and Partlists )
- Import/Export support via CSV (`_partlists_` column)
- GraphQL support
- Frontend block to render linked partlist products

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
