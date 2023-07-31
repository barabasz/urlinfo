# urlinfo

A simple wrapper around [cURL](https://www.php.net/manual/en/book.curl.php) that allows to display the most important information about the requested url in easy-to-read form.

## Usage

`sitespeed [options] URL`

Arguments:
    `URL`     url to request, could be with or without http(s):// prefix
Options:
    -c or --curlinfo: print cURL info
    -h or --help:     print this info and exit
    -i of --info:     print response headers
    -v or --version:  print version and exit
