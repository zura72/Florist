# Graph Report - Florist  (2026-11-02)

## Corpus Check
- 7 files · ~251,993 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 27 nodes · 32 edges · 4 communities
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- main.js
- cutoffText
- showPicture
- Design system Kembang Subuh

## God Nodes (most connected - your core abstractions)
1. `State komponen` - 9 edges
2. `Design system Kembang Subuh` - 8 edges
3. `render()` - 4 edges
4. `jakartaMinutes()` - 3 edges
5. `cutoffText()` - 3 edges
6. `paintCutoff()` - 3 edges
7. `rupiah()` - 2 edges
8. `showPicture()` - 2 edges
9. `setActive()` - 2 edges
10. `chosen()` - 2 edges

## Surprising Connections (you probably didn't know these)
- None detected - all connections are within the same source files.

## Import Cycles
- None detected.

## Communities (4 total, 0 thin omitted)

### Community 0 - "main.js"
Cohesion: 0.48
Nodes (6): chosen(), render(), rupiah(), setActive(), showPicture(), waLink()

### Community 1 - "cutoffText"
Cohesion: 1.00
Nodes (3): cutoffText(), jakartaMinutes(), paintCutoff()

### Community 2 - "showPicture"
Cohesion: 0.22
Nodes (9): Baris bunga (`.stem`), Foto pilihan di hero (`.pick button`), Kolom isian, Slot jadwal (`.slot`), State komponen, Tombol jeda (`.pause`), Tombol sekunder, Tombol tambah dan kurang (`.stepper`) (+1 more)

### Community 3 - "Design system Kembang Subuh"
Cohesion: 0.25
Nodes (7): Design system Kembang Subuh, Gerak, Huruf, Jarak dan sudut, Pengecualian yang disengaja, Tiga lapis token, Warna

## Knowledge Gaps
- **14 isolated node(s):** `Tiga lapis token`, `Warna`, `Huruf`, `Jarak dan sudut`, `Tombol utama (`.btn`)` (+9 more)
  These have ≤1 connection - possible missing edges or undocumented components.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `State komponen` connect `showPicture` to `Design system Kembang Subuh`?**
  _High betweenness centrality (0.283) - this node is a cross-community bridge._
- **Why does `Design system Kembang Subuh` connect `Design system Kembang Subuh` to `showPicture`?**
  _High betweenness centrality (0.258) - this node is a cross-community bridge._
- **What connects `Tiga lapis token`, `Warna`, `Huruf` to the rest of the system?**
  _14 weakly-connected nodes found - possible documentation gaps or missing edges._