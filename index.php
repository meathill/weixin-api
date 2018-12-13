<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 2018/12/12
 * Time: 9:46 PM
 */

require './config.php';


$url = isset($_GET['url']) ? $_GET['url'] : $_SERVER['HTTP_REFERER'];

$local = @file_get_contents('token.json');
$token = null;
if ($local) {
  $local = json_decode($local, true);
  if ($local['expiredAt'] > time()) {
    $token = $local['token'];
  }
}

if (!$token) {
  $app_id = WEIXIN_APP_ID;
  $secret = WEIXIN_APP_SECRET;
  $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=${app_id}&secret=${secret}";
  $response = file_get_contents($url);
  $response = json_decode($response, true);
  if (array_key_exists('errcode', $response) && $response['errcode'] != 0) {
    throw new Error($response['errmsg'], $response['errcode']);
  }

  $token = $response['access_token'];

  $local = [
    'token' => $token,
    'expiredAt' => $response['expires_in'] + time() - 10,
  ];
  file_put_contents('token.json', json_encode($local));
}

$local = @file_get_contents('ticket.json');
$ticket = null;
if ($local) {
  $local = json_decode($local, true);
  if ($local['expiredAt'] > time()) {
    $ticket = $local['ticket'];
  }
}

if (!$ticket) {
  $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=${token}&type=jsapi";
  $response = file_get_contents($url);
  $response = json_decode($response, true);
  if (array_key_exists('errcode', $response) && $response['errcode'] != 0) {
    throw new Error($response['errmsg'], $response['errcode']);
  }

  $ticket = $response['ticket'];

  $local = [
    'ticket' => $ticket,
    'expiredAt' => $response['expires_in'] + time() - 10,
  ];
  file_put_contents('ticket.json', json_encode($local));
}

try {
  $nonce = base64_encode(random_bytes(10));
} catch (Exception $e) {
  var_dump($e);
}
$timestamp = time();
$params = [
  'noncestr' => $nonce,
  'jsapi_ticket' => $ticket,
  'timestamp' => $timestamp,
  'url' => $url,
];
ksort($params);
$signature = sha1(urldecode(http_build_query($params)));

header('Content: text/javascript');

echo '
wx.config({
    appId: "' . WEIXIN_APP_ID . '",
    timestamp: ' . $timestamp . ',
    nonceStr: "' . $nonce . '",
    signature: "' . $signature . '",
    jsApiList: ["updateAppMessageShareData", "updateTimelineShareData"],
});
';
