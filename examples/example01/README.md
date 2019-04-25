
Example 01 Reposition
=====================

## Studi Kasus

Sebelumnya telah exists direktori:

- /destination/112233-direktur

dimana `112233` adalah unique identifier (`${id}`) dan `diretur` adalah informasi tambahan (`${additional_info}`).

Kemudian kita akan memindahkan file-file dengan pola sebagai berikut:

```
prefix_${date}_${id}.ext
```

Menuju target direktori yakni:

```
${id}${additional_info}*
```

Contoh:

- `/source/record_20190201210022_112233.mp4`
- `/source/record_20190201220022_112233.mp4`
- `/source/record_20190301050129_4455.mp4`
- `/source/record_20190301060722_4455.mp4`

Menjadi

- `/destination/112233-direktur/record_20190201210022_112233.mp4`
- `/destination/112233-direktur/record_20190201220022_112233.mp4`
- `/destination/4455           /record_20190301050129_4455.mp4`
- `/destination/4455           /record_20190301060722_4455.mp4`

## Test

Eksekusi file `./test.sh` pada direktori ini untuk mencoba studi kasus diatas.
