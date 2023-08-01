# urlinfo

A simple wrapper around [cURL](https://www.php.net/manual/en/book.curl.php) that allows to display the most important information about the requested url in easy-to-read form.

## Usage

`urlinfo [options] URL`

Arguments:

`URL` - url to request

Options:

    -c or --curlinfo: print cURL info
    -h or --help:     print this info and exit
    -i of --info:     print response headers
    -v or --version:  print version and exit

## Output Example

![Example of utlinfo output](https://raw.githubusercontent.com/barabasz/urlinfo/main/example.png)
