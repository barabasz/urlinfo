# urlinfo

`urlinfo` is a simple PHP CLI wrapper around [cURL](https://www.php.net/manual/en/book.curl.php) that allows to display the most important information about the requested url in easy-to-read form.

## Usage

`urlinfo [options] URL`

Arguments:

`URL` - url to request

Options:

    -b or --body       show body (only for unencoded text/plain content)
    -c or --curlinfo   print verbose cURL info (without SSL info)
    -f or --forcessl   ignore SSL errors
    -H or --help       print this info and exit
    -h or --headers    print verbose response headers (without cookies and CSP)
    -i or --ipinfo     print verbose ipinfo
    -m or --mute       mute standard output
    -p or --plaintext  force plain text content response
    -v or --version    print version and exit

## Output Example

![Example of utlinfo output](https://raw.githubusercontent.com/barabasz/urlinfo/main/example.png)


## TTFB (Time to First Byte)

Time to first byte is calculated as a time between final request (GET send by the client after TCP handshake and SSL handshake) and first byte recieved (difference between `time_pretransfer` and `time_starttransfer`). 
