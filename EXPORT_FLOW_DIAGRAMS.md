# Export System - Visual Flow Diagrams

## 1. Data Flow Diagram

```
┌────────────────────────────────────────────────────────────────────┐
│                        BANK TRANSACTIONS                          │
│                    (bank_transactions table)                       │
│  - transaction_id, amount, status, import_status, created_at      │
└────────────────────────┬───────────────────────────────────────────┘
                         │
                         ▼
            ┌────────────────────────┐
            │   ExportService        │
            ├────────────────────────┤
            │ exportAll()            │
            │ exportToday()          │
            │ getExportHistory()     │
            │ getTodayExportStatus() │
            └────────────┬───────────┘
                         │
         ┌───────────────┴───────────────┐
         │                               │
         ▼                               ▼
  ┌──────────────────┐          ┌──────────────────┐
  │  ALL Export      │          │ TODAY Export     │
  ├──────────────────┤          ├──────────────────┤
  │ Get all txns     │          │ Get yesterday   │
  │ Format with      │          │ + PAID status   │
  │ headers          │          │ + REJECTED tx   │
  │ to Excel         │          │ + extra columns │
  │ Style with color │          │                  │
  └────────┬─────────┘          └─────────┬────────┘
           │                               │
           ▼                               ▼
    ┌─────────────────┐            ┌─────────────────┐
    │ transactions_    │            │ transactions_   │
    │ all_2026-03-30_ │            │ today_2026-03-30│
    │ 14-30-45.xlsx   │            │ _06-00-12.xlsx  │
    └────────┬────────┘            └────────┬────────┘
             │                              │
             └──────────────┬───────────────┘
                            │
                            ▼
            ┌───────────────────────────────┐
            │  storage/app/exports/outbox   │
            │                               │
            │  (Excel files ready for use)  │
            └───────────────┬───────────────┘
                            │
                            ▼
            ┌───────────────────────────────┐
            │      exports_log table        │
            ├───────────────────────────────┤
            │ export_date                   │
            │ export_type   (ALL | TODAY)   │
            │ export_filename               │
            │ exported_rows                 │  ← Log Entry
            │ paid_rows                     │
            │ rejected_rows                 │
            │ status        (SUCCESS|FAIL)  │
            │ message                       │
            └───────────────────────────────┘
```

## 2. Request Flow Diagram

```
┌────────────────────────────────────────────────────────────────────┐
│                         REQUEST SOURCES                            │
└────────────────────────────────────────────────────────────────────┘
        │                          │                    │
        │                          │                    │
        ▼                          ▼                    ▼
    ┌────────┐              ┌──────────┐          ┌─────────┐
    │ API    │              │ Scheduler│          │ Console │
    │Request │              │ (Cron)   │          │ Command │
    └────┬───┘              └────┬─────┘          └────┬────┘
         │                       │                     │
         │                       │                     │
         ▼                       ▼                     ▼
    GET /export/all         ┌──────────────────┐   php artisan
    GET /export/today       │ 06:00 AM TODAY   │   export:trans
         │                  │ 23:59 TODAY      │   actions
         │                  └────────┬─────────┘
         │                           │
         └──────────────┬────────────┘
                        │
                        ▼
            ┌─────────────────────────┐
            │   ExportController      │
            │                         │
            │  exportAll()            │
            │  exportToday()          │
            │  history()              │
            │  statusToday()          │
            │  exportTransaction()    │
            └────────────┬────────────┘
                         │
                         ▼
            ┌─────────────────────────┐
            │   ExportService         │
            │                         │
            │  (Business Logic)       │
            └────────────┬────────────┘
                         │
                         ▼
            ┌─────────────────────────┐
            │  Excel Export Classes   │
            │                         │
            │  BankTransactionsExport │
            │  TodayTransactionsExport│
            └────────────┬────────────┘
                         │
                         ▼
            ┌─────────────────────────┐
            │  Export Files Created   │
            │                         │
            │  + Logged in Database   │
            └─────────────────────────┘
```

## 3. TODAY Export Data Flow

```
┌────────────────────────────────────────────────────────────┐
│             ALL BANK TRANSACTIONS (DB)                    │
│                                                             │
│  ID │ Status  │ Import_Status │ Created_At              │
│────┼─────────┼───────────────┼─────────────────────────│
│ 1  │ PAID    │ OK            │ 2026-03-28 10:00:00    │ ← INCLUDE
│ 2  │ PAID    │ OK            │ 2026-03-28 12:00:00    │ ← INCLUDE
│ 3  │ PENDING │ OK            │ 2026-03-28 14:00:00    │ ✗ EXCLUDE
│ 4  │ PAID    │ REJECTED      │ 2026-03-28 16:00:00    │ ← INCLUDE
│ 5  │ PAID    │ OK            │ 2026-03-29 10:00:00    │ ✗ EXCLUDE
│────┴─────────┴───────────────┴─────────────────────────┘
                         │
                         │ Filter Criteria:
                         │ created_at BETWEEN 2026-03-28 00:00 AND 2026-03-29 00:00
                         │ AND (bank_status = 'PAID' OR import_status = 'REJECTED')
                         │
                         ▼
         ┌───────────────────────────────┐
         │    FILTERED TRANSACTIONS      │
         │                               │
         │  Paid: 2                      │
         │  Rejected: 1                  │
         │  Total: 3                     │
         └───────────────┬───────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │   Create Excel Export         │
         │                               │
         │ - Header Row (bold, green)    │
         │ - Columns with extra fields   │
         │   • Reject Reason             │
         │   • Liquidation Date          │
         │ - 3 Data Rows                 │
         │                               │
         └───────────────┬───────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │  Save File                    │
         │                               │
         │  transactions_today_          │
         │  2026-03-30_06-00-12.xlsx    │
         │                               │
         └───────────────┬───────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │  Log Export in Database       │
         │                               │
         │  export_type: TODAY           │
         │  exported_rows: 3             │
         │  paid_rows: 2                 │
         │  rejected_rows: 1             │
         │  status: SUCCESS              │
         │                               │
         └───────────────────────────────┘
```

## 4. ALL Export Data Flow

```
┌────────────────────────────────────────────────────────────┐
│             ALL BANK TRANSACTIONS (DB)                    │
│                                                             │
│  ID │ Status  │ Import_Status │ Created_At              │
│────┼─────────┼───────────────┼─────────────────────────│
│ 1  │ PAID    │ OK            │ 2026-01-15 10:00:00    │ ← INCLUDE
│ 2  │ PENDING │ OK            │ 2026-02-20 12:00:00    │ ← INCLUDE
│ 3  │ FAILED  │ REJECTED      │ 2026-02-28 14:00:00    │ ← INCLUDE
│ 4  │ PAID    │ OK            │ 2026-03-10 16:00:00    │ ← INCLUDE
│ 5  │ PAID    │ OK            │ 2026-03-28 10:00:00    │ ← INCLUDE
│   ...                                            ...       │
│────┴─────────┴───────────────┴─────────────────────────┘
                         │
                         │ Filter Criteria:
                         │ Get ALL transactions
                         │ No date filter
                         │ No status filter
                         │
                         ▼
         ┌───────────────────────────────┐
         │    ALL TRANSACTIONS           │
         │                               │
         │  Total: 1250                  │
         │  Paid: 1100                   │
         │  Rejected: 150                │
         └───────────────┬───────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │   Create Excel Export         │
         │                               │
         │ - Header Row (bold, blue)     │
         │ - Standard Columns            │
         │   (no extra fields)           │
         │ - 1250 Data Rows              │
         │                               │
         └───────────────┬───────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │  Save File                    │
         │                               │
         │  transactions_all_            │
         │  2026-03-30_23-59-45.xlsx    │
         │                               │
         └───────────────┬───────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │  Log Export in Database       │
         │                               │
         │  export_type: ALL             │
         │  exported_rows: 1250          │
         │  paid_rows: 1100              │
         │  rejected_rows: 150           │
         │  status: SUCCESS              │
         │                               │
         └───────────────────────────────┘
```

## 5. Component Dependency Chart

```
┌─────────────────────────────────────────────────────────────┐
│                    LARAVEL APP                             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │         models/BankTransaction.php                   │  │
│  │         (Data Source)                               │  │
│  └──────────────────┬───────────────────────────────────┘  │
│                     │                                       │
│                     │ Uses (query builder)                  │
│                     │                                       │
│  ┌──────────────────▼───────────────────────────────────┐  │
│  │  Services/ExportService.php                         │  │
│  │  ───────────────────                                │  │
│  │  • exportAll()                                      │  │
│  │  • exportToday()                                    │  │
│  │  • getExportHistory()                               │  │
│  │  • getTodayExportStatus()                           │  │
│  └──┬────────────────────────────────────────────┬─────┘  │
│     │                                            │         │
│     │ Creates instances                         │ Logs    │
│     │                                            │         │
│  ┌──▼────────┐                        ┌─────────▼──────┐  │
│  │  Exports/ │                        │  ExportsLog    │  │
│  │  *.php    │                        │  (database)    │  │
│  │           │                        │                │  │
│  │ • Bx      │                        │  - export_type │  │
│  │ • Tx      │                        │  - status      │  │
│  └──┬────────┘                        │  - rows        │  │
│     │                                  └────────────────┘  │
│     │ Returns                                              │
│     │                                                      │
│  ┌──▼──────────────────────────────────────────────────┐  │
│  │  Http/Controllers/ExportController.php             │  │
│  │  ──────────────────────────────────────            │  │
│  │  • exportAll()      → GET /export/all             │  │
│  │  • exportToday()    → GET /export/today           │  │
│  │  • history()        → GET /export/history          │  │
│  │  • statusToday()    → GET /export/status/today    │  │
│  │  • exportTransaction() → GET /export/transaction  │  │
│  └──┬────────────────────────────────────────────────┘  │
│     │                                                      │
│     │ Registered in                                       │
│     │                                                      │
│  ┌──▼────────────────────────────────────────────────┐  │
│  │  routes/web.php                                   │  │
│  │  ─────────────────                               │  │
│  │  /export/* routes                               │  │
│  └────────────────────────────────────────────────┘  │
│                                                        │
│  ┌─────────────────────────────────────────────────┐  │
│  │  Console/Commands/ExportTransactionsCommand    │  │
│  │  ───────────────────────────────────          │  │
│  │  php artisan export:transactions             │  │
│  └──┬──────────────────────────────────────────┘  │
│     │                                              │
│     │ Scheduled by                                 │
│     │                                              │
│  ┌──▼──────────────────────────────────────────┐  │
│  │  Console/Kernel.php                          │  │
│  │  ─────────────────────                       │  │
│  │  schedule() method:                          │  │
│  │  • 06:00 AM → export:transactions --type=today│  │
│  │  • 23:59 PM → export:transactions --type=all│  │
│  └──────────────────────────────────────────────┘  │
│                                                        │
└────────────────────────────────────────────────────────┘

        │
        │ Output
        │
        ▼
    storage/app/exports/outbox/
    ├─ transactions_all_*.xlsx
    └─ transactions_today_*.xlsx
```

## 6. Timing Diagram

```
Timeline: 24-hour cycle

00:00 ├─ Yesterday ends / Today begins
      │  (Database cutoff for TODAY export)
      │
06:00 ├─ TODAY EXPORT RUNS
      │  └─ Get transactions from 2026-03-28 00:00 to 00:00
      │     └─ Filter: PAID + REJECTED status
      │     └─ Create transactions_today_2026-03-30_06-00-12.xlsx
      │     └─ Log: paid_rows, rejected_rows
      │
12:00 ├─ Business Hours
      │  └─ New transactions still coming in
      │
18:00 ├─ End of Business
      │  └─ More new transactions possible
      │
23:59 ├─ ALL EXPORT RUNS
      │  └─ Get all 1246 transactions (all dates, all statuses)
      │  └─ Create transactions_all_2026-03-30_23-59-45.xlsx
      │  └─ Log: total_rows, paid_rows, rejected_rows
      │
00:00 └─ Day ends → Cycle repeats
```

---

**All diagrams show the complete export system architecture and data flow.**
