# School holiday data

`school-holidays-de.json` contains German school holidays per federal state.

- Source: OpenHolidays API, https://www.openholidaysapi.org
- Retrieved: 2026-10-30
- Coverage: 2026 through 2029 is complete for the main holidays; 2030 onward is incomplete because the states have not published those dates yet.
- Licence: the file is a derived database under the Open Database License (ODbL) v1.0, see `LICENSE`. The code of this package is MIT-licensed; the ODbL applies to the contents of `data/` only.

## Refreshing

```bash
php tools/update-school-holidays.php
```

The tool fetches 2026-01-01 to 2031-12-31 in windows of at most 1095 days, removes duplicates by id, writes one row per state and sorts the result.

## Name mapping

| API name | type |
| --- | --- |
| Winterferien, Halbjahresferien, Halbjahrespause, Fastnachtsferien, Frühjahrsferien | `winter` |
| Osterferien | `easter` |
| Pfingstferien | `pentecost` |
| Sommerferien | `summer` |
| Herbstferien | `autumn` |
| Weihnachtsferien | `christmas` |

Everything else (for example single bridge days) is dropped.

## Known gaps

Hamburg publishes no separate Easter holidays; its spring break in March is mapped to `winter`.
