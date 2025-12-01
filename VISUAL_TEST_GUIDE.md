# Visual Test Guide - Stats Update Fix

## ✅ What You Should See Now

### Progress Section (During Sync)

```
┌─────────────────────────────────────────────────────────────┐
│  🔄 Synchronization in Progress                             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Progress: ████████████████████░░░░░░░  91%                │
│  Processing files...                                        │
│                                                              │
│  ┌──────────┬──────────┬──────────┬──────────┐            │
│  │Processed │ Success  │ Skipped  │  Failed  │            │
│  │   182    │   165    │    15    │     2    │  ← Numbers!│
│  └──────────┴──────────┴──────────┴──────────┘            │
│                                                              │
│  [ ⏸ Pause ]  [ ❌ Cancel ]                                 │
└─────────────────────────────────────────────────────────────┘
```

### Before Fix (Broken):

```
Progress: ████████████████████░░░░░░░  91%  ✅
Processed: 0  ❌
Success:   0  ❌
Skipped:   0  ❌
Failed:    0  ❌
```

### After Fix (Working):

```
Progress: ████████████████████░░░░░░░  91%  ✅
Processed: 182  ✅
Success:   165  ✅
Skipped:   15   ✅
Failed:    2    ✅
```

## 🔍 Check Browser Console

Open DevTools (F12) → Console tab

### You Should See:

```javascript
Progress response: {
  success: true,
  data: {
    status: "processing",
    progress: 91,
    result: {
      processed: 182,  // ✅ Not null!
      success: 165,    // ✅ Not null!
      skipped: 15,     // ✅ Not null!
      failed: 2,       // ✅ Not null!
      errors: [...]
    }
  }
}

Updating progress with data: {status: "processing", progress: 91, result: {...}}
```

### Before Fix (Broken):

```javascript
Progress response: {
  success: true,
  data: {
    status: "processing",
    progress: 91,
    result: null  // ❌ Problem here!
  }
}
```

## 📊 Database Check

```sql
SELECT 
    id,
    status,
    progress,
    JSON_EXTRACT(result, '$.processed') as processed,
    JSON_EXTRACT(result, '$.success') as success,
    JSON_EXTRACT(result, '$.skipped') as skipped,
    JSON_EXTRACT(result, '$.failed') as failed
FROM wp_aie_jobs 
WHERE type = 'media_sync' 
ORDER BY id DESC 
LIMIT 1;
```

### Expected Result:

```
+----+-----------+----------+-----------+---------+---------+--------+
| id | status    | progress | processed | success | skipped | failed |
+----+-----------+----------+-----------+---------+---------+--------+
| 42 | processing|    91.00 |       182 |     165 |      15 |      2 |
+----+-----------+----------+-----------+---------+---------+--------+
```

### Before Fix:

```
+----+-----------+----------+-----------+---------+---------+--------+
| id | status    | progress | processed | success | skipped | failed |
+----+-----------+----------+-----------+---------+---------+--------+
| 42 | processing|    91.00 |      NULL |    NULL |    NULL |   NULL |
+----+-----------+----------+-----------+---------+---------+--------+
```

## 🎬 Animation (Expected)

Watch the counters increment smoothly:

```
Second 0:   Progress 0%   | Processed 0   | Success 0   | Skipped 0
Second 2:   Progress 20%  | Processed 40  | Success 38  | Skipped 2
Second 4:   Progress 40%  | Processed 80  | Success 74  | Skipped 6
Second 6:   Progress 60%  | Processed 120 | Success 110 | Skipped 10
Second 8:   Progress 80%  | Processed 160 | Success 147 | Skipped 13
Second 10:  Progress 100% | Processed 200 | Success 184 | Skipped 16
```

## ✨ Real-Time Updates

Every 2 seconds, you should see:
- ✅ Progress bar fills
- ✅ Percentage increases
- ✅ Processed count goes up
- ✅ Success/Skipped/Failed update

## 🐛 Still Broken? Check:

### 1. Cache Issue
```bash
# Clear browser cache
Ctrl+F5 (Windows/Linux)
Cmd+Shift+R (Mac)

# Or hard refresh
Ctrl+Shift+Delete → Clear cache
```

### 2. Old Code Running
```bash
cd /home/brovatar/Local\ Sites/wp-advanced-import-export/app/public/wp-content/plugins/wp-advanced-import-export
npm run dev  # Rebuild assets
```

### 3. Database Not Updated
```sql
-- Check result field is not null
SELECT id, result FROM wp_aie_jobs WHERE type = 'media_sync' ORDER BY id DESC LIMIT 1;

-- If NULL, job might be using old code
-- Cancel and start new sync
```

### 4. Browser DevTools Console Shows Errors
```javascript
// If you see:
result.processed is undefined
// Then data structure is wrong

// Should see:
result = {processed: 182, success: 165, ...}
```

## 📝 Test Checklist

- [ ] Progress bar moves from 0% to 100%
- [ ] Processed counter increments (not stuck at 0)
- [ ] Success counter increments
- [ ] Skipped counter increments (if applicable)
- [ ] Failed counter increments (if errors)
- [ ] Console shows `result: {processed: X, ...}` not `result: null`
- [ ] Database `result` column contains JSON data
- [ ] Stats preserved after Pause/Resume

---

**All Green? You're Good! 🎉**

If any item fails, check the troubleshooting section above.
