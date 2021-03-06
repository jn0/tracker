<?php
if ((count($_REQUEST) != 1 or !isset($_REQUEST['u']))
and (count($_REQUEST) != 2 or !isset($_REQUEST['u']) or !isset($_REQUEST['n'])))
	header("Content-Type: text/plain; charset=UTF-8");

$u = isset($_REQUEST['u']) ? (" (".$_REQUEST['u'].")") : '';

$API_SPUTNIK = '';

function cache_file_name($id, $type, $suffix) {
    $u = isset($_REQUEST['u']) ? ("-".$_REQUEST['u']) : '';
    return "cache/map-$type$u-$id.$suffix";
}

function d_fract($dt, $q, $sig) {
    $o = null;
    if ($dt > $q) {
        $o = intdiv($dt, $q).$sig;
        $dt = $dt % $q;
    }
    return array($o, $dt);
}

function dhms($dt) {
    $o = array();

    list($f, $dt) = d_fract($dt, 604800, 'w');  if(!is_null($f)) $o[] = $f;
    list($f, $dt) = d_fract($dt, 86400, 'd');   if(!is_null($f)) $o[] = $f;
    list($f, $dt) = d_fract($dt, 3600, 'h');    if(!is_null($f)) $o[] = $f;
    list($f, $dt) = d_fract($dt, 60, 'm');      if(!is_null($f)) $o[] = $f;
    $o[] = $dt.'s';

    return implode(' ', $o);
}

function fetch($url) {
    global $php_errormsg;

    $data = '';
    $fp = fopen($url, 'r');
    if ($fp) {
        while (!feof($fp)) { $s = fread($fp, 4096); $data .= $s; }
        fclose($fp);
    } else die("\n\nURL [$url] msg [$php_errormsg]\n\n");
    return $data;
}

function save($fn, $data) {
    switch (gettype($data)) {
        case 'array':
            $data = implode("\n", $data) . "\n";
        case 'integer':
        case 'double':
            $data = ''.$data;
        case 'string': break;
        case 'boolean': $data = $data ? '=TRUE' : '=FALSE'; break;
        case 'NULL': $data = '=NULL'; break;
        default:
            $data = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            break;
    }
    $fp = fopen($fn, 'a');
    fputs($fp, $data);
    fclose($fp);

    if (substr($fn, -4) == '.png') {
        $o = array();
        $rc = 0;
        exec('optipng -quiet -force -fix -- '.$fn.' >/dev/null 2>&1', $o, $rc);
    }
}

function get_picture_mapbox($point, $id, $zoom=13, $bearing=0, $pitch=0) {
    global $MAPBOX;
    $MAPBOX = File('/home/jno/.jno/mapbox.com');
    $MAPBOX = trim($MAPBOX[0]);
    $fn = cache_file_name($id, 'mapbox', 'png');
    if (!file_exists($fn)) {
        $lon = $point['lon'];
        $lat = $point['lat'];
        $url = "https://api.mapbox.com/styles/v1/mapbox/streets-v10/static"
                ."/pin-s-1+0F0($lon,$lat)"
                ."/$lon,$lat,$zoom,$bearing,$pitch"
                ."/400x400"
                ."?access_token=".$MAPBOX
                .'';
        save($fn, fetch($url));
    }
    return $fn;
}

function get_picture_sputnik($point, $id, $zoom=13, $layer='map') {
    $fn = cache_file_name($id, 'sputnik', 'png');
    if (!file_exists($fn)) {
        $url = 'http://static-api.maps.sputnik.ru/v1/?'
                ."z=$zoom"
                .'&clng='.$point['lon'].'&clat='.$point['lat']
                .'&mlng='.$point['lon'].'&mlat='.$point['lat']
                ."&width=400&height=400"
                .'';
        # $url .= "&apikey=$API_SPUTNIK";

        save($fn, fetch($url));
    }
    return $fn;
}

function get_places_sputnik($point, $id) {
    global $API_SPUTNIK;

    $fn = cache_file_name($id, 'sputnik', 'cache');
    if (file_exists($fn)) {
        $pl = File($fn, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
        return $pl;
    }

    # lat     широта     55.76228158365787
    # lon     долгота     37.854766845703125
    # houses  true - поиск до здания, false - до района города     true
    # callback    имя callback-функции для запроса в форме JSONP   jsonp_123
    # apikey  API-ключ  5032f91e8da6431d8605-f9c0c9a00357

    $url = 'http://whatsthere.maps.sputnik.ru/point?'
            .'houses=true'
            .'&lat='.$point['lat']
            .'&lon='.$point['lon']
            .'';

    $data = fetch($url);
    $pl = array();

    $j = json_decode($data, true);
    # jq .result.address[].features[] < sputnik.json | jq .properties.display_name
    foreach($j['result']['address'] as $av) {
        foreach($av['features'] as $fv) {
                $pl[] = $fv['properties']['display_name'];
        }
    }
    save($fn, $pl);
    return $pl;
}

function get_picture_yandex($point, $id, $zoom=13, $layer='map') {
    $fn = cache_file_name($id, 'yandex', 'png');
    if (!file_exists($fn)) {
        $LL = $point['lon'].','.$point['lat'];

        $url = 'https://static-maps.yandex.ru/1.x/?lang=ru_RU';
        $url .= "&ll=$LL";
        $url .= "&size=400,400";
        $url .= "&z=$zoom";
        $url .= "&l=$layer";
        $url .= "&pt=$LL,".'pm2'.'db'.'m'.'1';

        save($fn, fetch($url));
    }
    return $fn;
}

function get_places_yandex($point, $id) {
    $fn = cache_file_name($id, 'yandex', 'cache');
    if (file_exists($fn)) {
        $pl = File($fn, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    } else {
        $LL = $point['lon'].','.$point['lat'];
        $pl = array();

        $acc = $point['acc'];    /* accuracy, meters */
        $lat = $point['lat'];  	/* latitude, degree */
        $lon = $point['lon'];  	/* longitude, degree */

        $mdd = 40008552. / 360.;  /* meridium degree, meters */
        $eqd = 40075000. / 360.;  /* equatorial degree, meters */

        $latd = $eqd * cos($lat * M_PI / 180.);   /* latitude degree, meters */
        $lond = $mdd;                        /* longitude degree, meters */

        $delta = $acc * 4.;        /* the span, meters */

        $slat = $delta / $latd;
        $slon = $delta / $lond;

        while (true) {
            $url = 'https://geocode-maps.yandex.ru/1.x/?lang=ru_RU';

            $url .= "&geocode=$LL";
            $url .= "&sco=longlat";
            $url .= "&ll=$LL";
            $url .= "&spn=$slon,$slat";
            $url .= "&kind=house";
            $url .= "&format=json";
            $url .= "&rspn=1";
            $url .= "&results=10";

            # echo "<br /><pre>$url</pre><br />\n";

            $data = fetch($url);

            $j = json_decode($data, true);
            # jq .response.GeoObjectCollection.featureMember[] < "$1" |
            $j = $j['response']['GeoObjectCollection']['featureMember'];
            if (count($j) == 0 and $slat < 0.003 and $slon < 0.003) {
                $slat += 0.0005;
                $slon += 0.0005;
                sleep(1);
                continue;
            }

            break;
        }

        foreach($j as $k=>$v) {
            # jq '.GeoObject.metaDataProperty.GeocoderMetaData|select(.precision=="exact")|.text'
            $d = $v['GeoObject']['metaDataProperty']['GeocoderMetaData'];
            if ($d['precision'] != 'exact') continue;
            $pl[] = $d['text'];
        }
 
        save($fn, $pl);
    }
    return $pl;
}

function show_request() {
    $u = $_REQUEST['u'];
    $c = isset($_REQUEST['n']) ?  $_REQUEST['n'] : null;
    $matched = false;
    $point = array('lat'=>null, 'lon'=>null, 'time'=>null, 'acc'=>null);
    $last = array();
    $N = count($point);
    $n = 0;
    $cnt = 0;

    $fp = fopen("track.log", "r");
    while (!feof($fp)) {
        $s = rtrim(fgets($fp, 4096));

        if (substr($s, 0, 1) == '#') {
            $matched = false; # new section

            if ($n == $N) {
                # point accumulated
                $cnt++;
                if (!is_null($c)) {
                    if ($c == $cnt) {
                        $cnt--;
                        break;
                    }
                }
                foreach($point as $k=>$v) {
                    $last[$k] = $v;
                    $point[$k] = null;
                }
            }
            $n = 0; # drop point

            $a = explode(' ', $s);
            if (count($a) == 5) {
                # user section: <hash> <date> <time> <tz> <user>
                $matched = $a[4] == $u;
                # if ($matched) echo "$s\n";
            }

            continue;
        }

        if ($matched and $s) {
            list($k, $v) = explode('=', $s, 2);
            if (array_key_exists($k, $point)) {
                $point[$k] = $v;
                $n++;
            }
        }
    }
    fclose($fp);
    if ($n == $N) {
        # point accumulated
        $cnt++;
        foreach($point as $k=>$v) {
            $last[$k] = $v;
        }
    }

    $dt = dhms(time() - strtotime($last['time']));
?><!DOCTYPE html>
<html>
<head>
 <meta charset="utf-8">
 <meta http-equiv="refresh" content="35">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title><?php echo("$cnt".($u ? " ($u)" : '')) ?></title>
 <link rel="stylesheet" href="bootstrap.min.css">
 <style>
  body { font-size: 12pt }
  div#mytop { max-width: 800px; margin-left: auto; margin-right: auto; }
  div.mymap { width: 400px; height: 400px; float: left; }
  div.myplaces { float: left; width: 400px; }
  /* ul#myargs { max-width: 300px; } */
  ul#myargs>li { display: inline-block; width: 25ex; }
  hr { clear: both }
  button { width: 4em; float: left; margin-top: 10pt; vertical-align: middle; margin-right: 10px; }
 </style>
</head>
<body class=container>
<div id=mytop>
<?php
    echo '<button autofocus value="Refresh" onClick="window.location.reload()">'
        .'<i class="glyphicon glyphicon-refresh" style="color:red; font-size:24pt"></i>'
        .'</button>'
        ."\n";

    echo "<ul id=myargs><li>point: $cnt".($u ? " ($u)" : '')."</li>\n<li>back: $dt</li>\n";
    $show = array('time', 'acc');
    foreach ($show as $k) {
        if (isset($last[$k])) {
            $v = str_replace('T', ' ', explode(".", $last[$k])[0]); // preprocess it a bit...
            echo "<li>$k: ".substr($v, 0, 19)."</li>\n";
        }
    }
    echo "</ul>\n";

    echo "<hr />\n";

    $fn = get_picture_mapbox($last, $cnt);
    echo "<div class=mymap><a href=\"#\"><img src=\"$fn\" alt=\"[mapbox OSM]\" /></a></div>\n";
/*
    $fn = get_picture_yandex($last, $cnt);
    $url = 'https://yandex.ru/maps/?mode=search&text='.$last['lat'].','.$last['lon'];
    echo "<div class=mymap><a href=\"$url\"><img src=\"$fn\" alt=\"[yandex map]\" /></a></div>\n";

    $fn = get_picture_sputnik($last, $cnt);
    $url = 'https://maps.sputnik.ru/?lat='.$last['lat'].'&lon='.$last['lon'];
    echo "<div class=mymap><a href=\"$url\"><img src=\"$fn\" alt=\"[sputnik map]\" /></a></div>\n";

    echo "<hr />\n";
*/

    $pl = get_places_yandex($last, $cnt);
    reset($pl);
    echo "<div class=myplaces><ul>\n";
    $LL = $last['lon'].','.$last['lat'];
    for ($i = 0; $i < count($pl); $i++) {
        $url = "https://yandex.ru/maps/213/moscow/?mode=search"
            ."&ll=$LL&z=17&text=".urlencode($pl[$i]);
        echo "<li><a href=\"$url\">".$pl[$i]."</a></li>\n";
    }
    echo "</ul></div>\n";

    /*
    $pl = get_places_sputnik($last, $cnt);
    reset($pl);
    echo "<div class=myplaces><ul>\n";
    $LL = $last['lon'].','.$last['lat'];
    for ($i = 0; $i < count($pl); $i++) {
        $url = "https://maps.sputnik.ru/?zoom=14"
            .'&lng='.$last['lon']
            .'&lat='.$last['lat']
            ."&q=".urlencode($pl[$i]);
        echo "<li><a href=\"$url\">".$pl[$i]."</a></li>\n";
    }
    echo "</ul></div>\n";
    */

?></div></body></html>
<?php
}

function get_request() {
    $u = isset($_REQUEST['u']) ? $_REQUEST['u'] : false;
    $data = array();
    $data[] = '# '.strftime('%F %T %z').($u ? " $u" : '');

    $srv = array('REMOTE_ADDR', 'HTTP_REFERER', 'HTTP_X_FORWARDED_FOR');
    foreach($srv as $k) {
        if (isset($_SERVER[$k]))
            $data[] = "/ $k=".$_SERVER[$k];
    }

    $hdr = apache_request_headers();
    if (!isset($hdr['User-Agent']) or $hdr['User-Agent'] != 'okhttp/3.7.0') {
        foreach($hdr as $k => $v) { $data[] = "% $k=$v"; }
    }

    foreach($_REQUEST as $k => $v) {
        if ($k == 'u' or $v == '' or $v == '0' or $v == '0.0')
            continue;
        $data[] = "$k=$v";
    }
    return $data;
}

if (count($_REQUEST) == 0) {
    echo "empty\n";
} elseif (count($_REQUEST) == 1 and isset($_REQUEST['u'])) {
    show_request();
} elseif (count($_REQUEST) == 2 and isset($_REQUEST['u']) and isset($_REQUEST['n'])) {
    show_request();
} else {
    echo "# ".$_SERVER['REMOTE_ADDR']."$u\n";
    save("track.log", get_request());
    echo "ok$u\n";
}
# vim: set ft=php ai et ts=4 sts=4 sw=4 : EOF ?>
