# tracker
support generic GPS tackers

Many GPS loggers/trackers allow to publish points by performing a GET
request to arbitrary HTTP server.

This "tracker" allow to store it in a plain text and retrive.

The only point is to add `&u=USER` argument, where *USER* is an arbitrary string
to put apart possibly multiple users of the service.

When invoked with only `&u=USER` argument, it will display the last point reported.

The current implementation supposes the use of [GPS Logger](https://code.mendhak.com/gpslogger/).

Graphics and geocoding by [Yandex](https://maps.ya.ru) free API.

## Sample log entries

    # 2018-03-15 09:35:34 +0300 user1
    / REMOTE_ADDR=1.173.86.123
    lat=5.75641094
    lon=7.56704068
    time=2018-03-15T06:35:34.000Z
    s=3.18
    sat=12
    alt=139.0
    acc=10.618999481201172
    prov=gps
    # 2018-03-15 09:36:45 +0300 user1
    / REMOTE_ADDR=1.173.86.123
    lat=5.756657
    lon=7.5658873
    time=2018-03-15T06:36:25.226Z
    acc=24.836999893188477
    prov=network

## GPS Logger Screenshot

This picture was grabbed from GPS Logger site:

![Custom URL Setup](img/21sslvalidation.gif)
