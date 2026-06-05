# ETechFlow_VariantLinks

Dynamic **"View Other Options / Finishes / Sizes"** product-detail-page (PDP) buttons for
Magento 2 (Hyvä), driven by per-product link attributes — replacing hardcoded
in-description buttons and stripping the old ones at render time.

## What it does

Many catalogues link product variants ("see this lock in another finish/size") with
anchors hand-baked into the product description HTML. That's unmaintainable: the links
rot, the markup is inconsistent, and the domain is hardwired. This module replaces that
pattern with three editable product attributes and renders clean, on-brand buttons on the
PDP.

- Adds three per-product attributes — `variant_options_url`, `variant_finishes_url`,
  `variant_sizes_url` — each holding a **relative** URL (path + filter query), e.g.
  `/euro-cylinders?manufacturer=2081`. The frontend prepends the store base URL, so the
  domain is always correct. A blank value hides that button.
- Editable in the admin product form (group **Variant Links**) and importable via the
  standard product CSV.
- **Legacy stripping:** removes old hand-coded "View Other …" anchors from the
  description at render time, so the migration is reversible — the source description is
  untouched.
- **Dynamic mode** (optional, off by default): instead of stored URLs, build the target
  link at render time from a category + filter attribute, via `DynamicLinkBuilder` /
  `FilterUrlBuilder`.
- Bulk import + a legacy-button migration CLI for one-shot catalogue conversion.

## Install

```bash
composer require etechflow/module-variant-links
bin/magento module:enable ETechFlow_VariantLinks
bin/magento setup:upgrade
bin/magento setup:di:compile        # production
```

The `setup:upgrade` runs the data patch that creates the three attributes.

## Configuration

**Stores → Configuration → ETechFlow → Variant Links** (`etechflow_variantlinks/*`):

| Setting | Default | Purpose |
|---|---|---|
| `buttons/enabled` | `variant_finishes,variant_sizes` | Which buttons render |
| `buttons/options_label` | `View Other Options` | Label for the options button |
| `buttons/finishes_label` | `View Other Finishes` | Label for the finishes button |
| `buttons/sizes_label` | `View Other Sizes` | Label for the sizes button |
| `buttons/strip_legacy` | `1` | Strip old in-description buttons at render time |
| `dynamic/enabled` | `0` | Build links at render time instead of from stored URLs |
| `dynamic/default_size_attribute` | — | Attribute used for dynamic size links |
| `dynamic/size_attribute_map` | — | Per-category size-attribute overrides |

## Rendering

Works out of the box — no theme edits required:

- **Buttons** render via `view/frontend/layout/catalog_product_view.xml`, which injects
  a block (bound to `ViewModel\VariantLinks`) into `product.info.main`, just after the
  price. The container exists in both Luma and Hyvä. To move the buttons, override that
  layout file in your own theme.
- **Legacy stripping** runs through a plugin on `Magento\Catalog\Helper\Output` — both
  Luma and Hyvä render `description` / `short_description` through it — so the old
  hand-coded anchors are cleaned at render time on every theme. The stored description is
  untouched (reversible); toggle with `buttons/strip_legacy`.

> Note: `FilterUrlBuilder` is intentionally kept as a separate seam so a future SEO
> layered-navigation module can share readable filter-URL generation.

> Note: `FilterUrlBuilder` is intentionally kept as a separate seam so a future SEO
> layered-navigation module can share readable filter-URL generation.

## CLI

```bash
bin/magento etechflow:variantlinks:import        # bulk import variant link attributes
bin/magento etechflow:variantlinks:migrate-legacy # convert hardcoded in-description buttons
```

## Requirements

- PHP >= 8.1
- Magento 2 (`magento/framework`, `magento/module-catalog`)

## License

Proprietary — © ETechFlow.
