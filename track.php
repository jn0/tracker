<?php
if (count($_REQUEST) != 1 or !isset($_REQUEST['u']))
	header("Content-Type: text/plain; charset=UTF-8");

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

function get_picture($point, $id, $zoom=13, $layer='map') {
    $fn = "map-$id.png";
    if (!file_exists($fn)) {
        $LL = $point['lon'].','.$point['lat'];

        $url = 'https://static-maps.yandex.ru/1.x/?lang=ru_RU';
        $url .= "&ll=$LL";
        $url .= "&size=450,450";
        $url .= "&z=$zoom";
        $url .= "&l=$layer";
        $url .= "&pt=$LL,".'pm2'.'db'.'m'.'1';

        $fp = fopen($url, 'r');
        $data = '';
        while (!feof($fp)) { $s = fread($fp, 4096); $data .= $s; }
        fclose($fp);

        $fp = fopen($fn, 'w');
        fputs($fp, $data);
        fclose($fp);
    }
    return $fn;
}

function get_places($point, $id) {
    $fn = "map-$id.cache";
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

            $fp = fopen($url, 'r');
            $data = '';
            while (!feof($fp)) { $s = fread($fp, 4096); $data .= $s; }
            fclose($fp);

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
 
        $fp = fopen($fn, 'w');
        fputs($fp, implode("\n", $pl));
        fclose($fp);

    }
    return $pl;
}

function show_request() {
    $u = $_REQUEST['u'];
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

        if ($matched) {
            list($k, $v) = explode('=', $s, 2);
            if (array_key_exists($k, $point)) {
                $point[$k] = $v;
                $n++;
            }
        }
    }
    fclose($fp);

    $dt = dhms(time() - strtotime($last['time']));
?><!DOCTYPE html>
<html>
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <style>
  div#top { max-width: 800px; margin-left: auto; margin-right: auto; }
  div#map { width: 450px; height: 450px; float: left; }
  div#places { float: left; width: 350px; }
  ul#args>li { display: inline-block; width: 32ex; }
 </style>
</head>
<body>
<div id=top>
<?php
    echo "<ul id=args><li>point: $cnt</li>\n<li>back: $dt</li>\n";
    foreach($last as $k=>$v) {
        echo "<li>$k: $v</li>\n";
    }
    echo "</ul>\n<hr />\n";
    $fn = get_picture($last, $cnt);
    $url = 'https://yandex.ru/maps/?mode=search&text='.$last['lat'].','.$last['lon'];
    echo "<div id=map><a href=\"$url\"><img src=\"$fn\" alt=\"[map]\" /></a></div>\n";
    $pl = get_places($last, $cnt);
    reset($pl);
    echo "<div id=places><ul>\n";
    $LL = $last['lon'].','.$last['lat'];
    for ($i = 0; $i < count($pl); $i++) {
        $url = "https://yandex.ru/maps/213/moscow/?mode=search"
            ."&ll=$LL&z=17&text=".urlencode($pl[$i]);
        echo "<li><a href=\"$url\">".$pl[$i]."</a></li>\n";
    }
    echo "</ul></div>\n";
?></div></body></html>
<?php
}

function save_request($fp) {
    if (isset($_SERVER['REMOTE_ADDR']))
        fputs($fp, "/ REMOTE_ADDR=".$_SERVER['REMOTE_ADDR']."\n");
    if (isset($_SERVER['HTTP_REFERER']))
        fputs($fp, "/ HTTP_REFERER=".$_SERVER['HTTP_REFERER']."\n");
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        fputs($fp, "/ HTTP_X_FORWARDED_FOR=".$_SERVER['HTTP_X_FORWARDED_FOR']."\n");

    $hdr = apache_request_headers();
    if (!isset($hdr['User-Agent']) or $hdr['User-Agent'] != 'okhttp/3.7.0') {
        foreach($hdr as $k => $v) { fputs($fp, "% $k=$v\n"); }
    }

    foreach($_REQUEST as $k => $v) {
        if ($k == 'u' or $v == '' or $v == '0' or $v == '0.0')
            continue;
        fputs($fp, "$k=$v\n");
    }
}

if (count($_REQUEST) == 0) {
    echo "empty\n";
} elseif (count($_REQUEST) == 1 and isset($_REQUEST['u'])) {
    show_request();
} elseif (isset($_REQUEST['u'])) {
    $u = $_REQUEST['u'];
    echo "# ".$_SERVER['REMOTE_ADDR']."; u=$u \n";
    $fp = fopen("track.log", "a");
    fputs($fp, '# '.strftime('%F %T %z')." $u\n");
    save_request($fp);
    fclose($fp);
    echo "ok ($u)\n";
} else {
    echo "# ".$_SERVER['REMOTE_ADDR']." \n";
    $fp = fopen("track.log", "a");
    fputs($fp, '# '.strftime('%F %T %z%n'));
    save_request($fp);
    fclose($fp);
    echo "ok\n";
}
?>
