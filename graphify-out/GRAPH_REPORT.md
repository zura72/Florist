# Graph Report - Florist  (2026-11-06)

## Corpus Check
- 34 files · ~358,666 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 234 nodes · 391 edges · 24 communities (23 shown, 1 thin omitted)
- Extraction: 95% EXTRACTED · 5% INFERRED · 0% AMBIGUOUS · INFERRED: 18 edges (avg confidence: 0.77)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- main.js
- checkout.js
- showPicture
- Design system Kembang Subuh
- auth.php
- admin.js
- ui.php
- ks_db
- pesanan.js
- floral.js
- Pembayaran online (Midtrans Snap)
- load_order

## God Nodes (most connected - your core abstractions)
1. `ks_db()` - 16 edges
2. `init()` - 14 edges
3. `Design system Kembang Subuh` - 12 edges
4. `cartIds()` - 9 edges
5. `Pembayaran online (Midtrans Snap)` - 9 edges
6. `e()` - 7 edges
7. `ks_config()` - 7 edges
8. `renderLines()` - 7 edges
9. `removeLine()` - 7 edges
10. `changeQty()` - 7 edges

## Surprising Connections (you probably didn't know these)
- `adm_login_locked()` --calls--> `ks_db()`  [INFERRED]
  admin/inc/auth.php → api/bootstrap.php
- `adm_attempt_login()` --calls--> `ks_db()`  [INFERRED]
  admin/inc/auth.php → api/bootstrap.php
- `adm_snapshot()` --calls--> `ks_db()`  [INFERRED]
  admin/inc/data.php → api/bootstrap.php
- `adm_schedule()` --calls--> `ks_db()`  [INFERRED]
  admin/inc/data.php → api/bootstrap.php
- `adm_latest_orders()` --calls--> `ks_db()`  [INFERRED]
  admin/inc/data.php → api/bootstrap.php

## Import Cycles
- None detected.

## Communities (24 total, 1 thin omitted)

### Community 0 - "main.js"
Cohesion: 0.20
Nodes (12): applyFilter(), cutoffText(), filterSmoothly(), jakartaMinutes(), lineMotion(), nameCards(), paintCutoff(), renderCart() (+4 more)

### Community 1 - "checkout.js"
Cohesion: 0.10
Nodes (44): addDays(), cartIds(), changeQty(), cleanText(), clientMinDate(), collapseLine(), demoPay(), el() (+36 more)

### Community 2 - "showPicture"
Cohesion: 0.13
Nodes (19): ks_apply_midtrans_status(), ks_config(), ks_consume_stock(), ks_delivery_slots(), ks_demo_payments_on(), ks_fail(), ks_json(), ks_map_midtrans_status() (+11 more)

### Community 3 - "Design system Kembang Subuh"
Cohesion: 0.11
Nodes (18): Design system Kembang Subuh, Filter (`.pill`), Foto, Gerak, Huruf, Jarak dan sudut, Kartu produk (`.card`), Keranjang dan pembayaran (+10 more)

### Community 4 - "auth.php"
Cohesion: 0.18
Nodes (15): adm_attempt_login(), adm_boot(), adm_csrf_field(), adm_csrf_token(), adm_csrf_valid(), adm_is_https(), adm_login_locked(), adm_redirect() (+7 more)

### Community 5 - "admin.js"
Cohesion: 0.20
Nodes (14): angka(), Chart(), compact(), countUp(), init(), load(), misc(), niceMax() (+6 more)

### Community 6 - "ui.php"
Cohesion: 0.21
Nodes (11): adm_take_flash(), adm_head(), adm_icon(), adm_layout_start(), adm_sprite(), angka(), e(), pay_badge() (+3 more)

### Community 7 - "ks_db"
Cohesion: 0.22
Nodes (12): adm_latest_orders(), adm_schedule(), adm_series(), adm_snapshot(), adm_sqlts(), adm_status_breakdown(), adm_top_products(), adm_totals() (+4 more)

### Community 8 - "pesanan.js"
Cohesion: 0.25
Nodes (16): burst(), demo(), fetchStatus(), formatDate(), render(), renderError(), renderSummary(), renderTrack() (+8 more)

### Community 9 - "floral.js"
Cohesion: 0.31
Nodes (6): buildPetals(), petal(), reduced(), sizeLayer(), symbolSvg(), syncPause()

### Community 10 - "Pembayaran online (Midtrans Snap)"
Cohesion: 0.20
Nodes (9): 1. Ambil kunci sandbox Midtrans, 2. Isi `api/config.php`, 3. Atur URL di dashboard Midtrans, 4. Uji di sandbox, 5. Cara kerja keamanan (ringkas), 6. Pindah ke produksi, Berkas yang terlibat, Checklist sebelum online (+1 more)

## Knowledge Gaps
- **23 isolated node(s):** `Berkas yang terlibat`, `1. Ambil kunci sandbox Midtrans`, `2. Isi `api/config.php``, `3. Atur URL di dashboard Midtrans`, `4. Uji di sandbox` (+18 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **1 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `ks_db()` connect `ks_db` to `showPicture`, `auth.php`?**
  _High betweenness centrality (0.059) - this node is a cross-community bridge._
- **Why does `adm_layout_start()` connect `ui.php` to `auth.php`?**
  _High betweenness centrality (0.032) - this node is a cross-community bridge._
- **Why does `adm_attempt_login()` connect `auth.php` to `ks_db`?**
  _High betweenness centrality (0.029) - this node is a cross-community bridge._
- **Are the 10 inferred relationships involving `ks_db()` (e.g. with `adm_attempt_login()` and `adm_login_locked()`) actually correct?**
  _`ks_db()` has 10 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Berkas yang terlibat`, `1. Ambil kunci sandbox Midtrans`, `2. Isi `api/config.php`` to the rest of the system?**
  _23 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `checkout.js` be split into smaller, more focused modules?**
  _Cohesion score 0.09948979591836735 - nodes in this community are weakly interconnected._
- **Should `showPicture` be split into smaller, more focused modules?**
  _Cohesion score 0.12615384615384614 - nodes in this community are weakly interconnected._