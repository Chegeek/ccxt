<?php

namespace ccxt;

class anxpro extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'anxpro',
            'name' => 'ANXPro',
            'countries' => array ( 'JP', 'SG', 'HK', 'NZ' ),
            'version' => '2',
            'rateLimit' => 1500,
            'has' => array (
                'CORS' => false,
                'fetchTrades' => false,
                'withdraw' => true,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/27765983-fd8595da-5ec9-11e7-82e3-adb3ab8c2612.jpg',
                'api' => 'https://anxpro.com/api',
                'www' => 'https://anxpro.com',
                'doc' => array (
                    'http://docs.anxv2.apiary.io',
                    'https://anxpro.com/pages/api',
                ),
            ),
            'api' => array (
                'public' => array (
                    'get' => array (
                        '{currency_pair}/money/ticker',
                        '{currency_pair}/money/depth/full',
                        '{currency_pair}/money/trade/fetch', // disabled by ANXPro
                    ),
                ),
                'private' => array (
                    'post' => array (
                        '{currency_pair}/money/order/add',
                        '{currency_pair}/money/order/cancel',
                        '{currency_pair}/money/order/quote',
                        '{currency_pair}/money/order/result',
                        '{currency_pair}/money/orders',
                        'money/{currency}/address',
                        'money/{currency}/send_simple',
                        'money/info',
                        'money/trade/list',
                        'money/wallet/history',
                    ),
                ),
            ),
            'markets' => array (
                'BTC/USD' => array ( 'id' => 'BTCUSD', 'symbol' => 'BTC/USD', 'base' => 'BTC', 'quote' => 'USD', 'multiplier' => 100000 ),
                'BTC/HKD' => array ( 'id' => 'BTCHKD', 'symbol' => 'BTC/HKD', 'base' => 'BTC', 'quote' => 'HKD', 'multiplier' => 100000 ),
                'BTC/EUR' => array ( 'id' => 'BTCEUR', 'symbol' => 'BTC/EUR', 'base' => 'BTC', 'quote' => 'EUR', 'multiplier' => 100000 ),
                'BTC/CAD' => array ( 'id' => 'BTCCAD', 'symbol' => 'BTC/CAD', 'base' => 'BTC', 'quote' => 'CAD', 'multiplier' => 100000 ),
                'BTC/AUD' => array ( 'id' => 'BTCAUD', 'symbol' => 'BTC/AUD', 'base' => 'BTC', 'quote' => 'AUD', 'multiplier' => 100000 ),
                'BTC/SGD' => array ( 'id' => 'BTCSGD', 'symbol' => 'BTC/SGD', 'base' => 'BTC', 'quote' => 'SGD', 'multiplier' => 100000 ),
                'BTC/JPY' => array ( 'id' => 'BTCJPY', 'symbol' => 'BTC/JPY', 'base' => 'BTC', 'quote' => 'JPY', 'multiplier' => 100000 ),
                'BTC/GBP' => array ( 'id' => 'BTCGBP', 'symbol' => 'BTC/GBP', 'base' => 'BTC', 'quote' => 'GBP', 'multiplier' => 100000 ),
                'BTC/NZD' => array ( 'id' => 'BTCNZD', 'symbol' => 'BTC/NZD', 'base' => 'BTC', 'quote' => 'NZD', 'multiplier' => 100000 ),
                'LTC/BTC' => array ( 'id' => 'LTCBTC', 'symbol' => 'LTC/BTC', 'base' => 'LTC', 'quote' => 'BTC', 'multiplier' => 100000 ),
                'STR/BTC' => array ( 'id' => 'STRBTC', 'symbol' => 'STR/BTC', 'base' => 'STR', 'quote' => 'BTC', 'multiplier' => 100000000 ),
                'XRP/BTC' => array ( 'id' => 'XRPBTC', 'symbol' => 'XRP/BTC', 'base' => 'XRP', 'quote' => 'BTC', 'multiplier' => 100000000 ),
                'DOGE/BTC' => array ( 'id' => 'DOGEBTC', 'symbol' => 'DOGE/BTC', 'base' => 'DOGE', 'quote' => 'BTC', 'multiplier' => 100000000 ),
            ),
            'fees' => array (
                'trading' => array (
                    'maker' => 0.3 / 100,
                    'taker' => 0.6 / 100,
                ),
            ),
        ));
    }

    public function fetch_balance ($params = array ()) {
        $response = $this->privatePostMoneyInfo ();
        $balance = $response['data'];
        $currencies = is_array ($balance['Wallets']) ? array_keys ($balance['Wallets']) : array ();
        $result = array ( 'info' => $balance );
        for ($c = 0; $c < count ($currencies); $c++) {
            $currency = $currencies[$c];
            $account = $this->account ();
            if (is_array ($balance['Wallets']) && array_key_exists ($currency, $balance['Wallets'])) {
                $wallet = $balance['Wallets'][$currency];
                $account['free'] = floatval ($wallet['Available_Balance']['value']);
                $account['total'] = floatval ($wallet['Balance']['value']);
                $account['used'] = $account['total'] - $account['free'];
            }
            $result[$currency] = $account;
        }
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $limit = null, $params = array ()) {
        $response = $this->publicGetCurrencyPairMoneyDepthFull (array_merge (array (
            'currency_pair' => $this->market_id($symbol),
        ), $params));
        $orderbook = $response['data'];
        $t = intval ($orderbook['dataUpdateTime']);
        $timestamp = intval ($t / 1000);
        return $this->parse_order_book($orderbook, $timestamp, 'bids', 'asks', 'price', 'amount');
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $response = $this->publicGetCurrencyPairMoneyTicker (array_merge (array (
            'currency_pair' => $this->market_id($symbol),
        ), $params));
        $ticker = $response['data'];
        $t = intval ($ticker['dataUpdateTime']);
        $timestamp = intval ($t / 1000);
        $bid = $this->safe_float($ticker['buy'], 'value');
        $ask = $this->safe_float($ticker['sell'], 'value');
        $baseVolume = floatval ($ticker['vol']['value']);
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => floatval ($ticker['high']['value']),
            'low' => floatval ($ticker['low']['value']),
            'bid' => $bid,
            'ask' => $ask,
            'vwap' => null,
            'open' => null,
            'close' => null,
            'first' => null,
            'last' => floatval ($ticker['last']['value']),
            'change' => null,
            'percentage' => null,
            'average' => floatval ($ticker['avg']['value']),
            'baseVolume' => $baseVolume,
            'quoteVolume' => null,
            'info' => $ticker,
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        throw new ExchangeError ($this->id . ' switched off the trades endpoint, see their docs at http://docs.anxv2.apiary.io/reference/market-data/currencypairmoneytradefetch-disabled');
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $market = $this->market ($symbol);
        $order = array (
            'currency_pair' => $market['id'],
            'amount_int' => intval ($amount * 100000000), // 10^8
        );
        if ($type === 'limit') {
            $order['price_int'] = intval ($price * $market['multiplier']); // 10^5 or 10^8
        }
        $order['type'] = ($side === 'buy') ? 'bid' : 'ask';
        $result = $this->privatePostCurrencyPairMoneyOrderAdd (array_merge ($order, $params));
        return array (
            'info' => $result,
            'id' => $result['data'],
        );
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        return $this->privatePostCurrencyPairMoneyOrderCancel (array ( 'oid' => $id ));
    }

    public function get_amount_multiplier ($currency) {
        if ($currency === 'BTC') {
            return 100000000;
        } else if ($currency === 'LTC') {
            return 100000000;
        } else if ($currency === 'STR') {
            return 100000000;
        } else if ($currency === 'XRP') {
            return 100000000;
        } else if ($currency === 'DOGE') {
            return 100000000;
        }
        return 100;
    }

    public function withdraw ($currency, $amount, $address, $tag = null, $params = array ()) {
        $this->load_markets();
        $multiplier = $this->get_amount_multiplier ($currency);
        $response = $this->privatePostMoneyCurrencySendSimple (array_merge (array (
            'currency' => $currency,
            'amount_int' => intval ($amount * $multiplier),
            'address' => $address,
        ), $params));
        return array (
            'info' => $response,
            'id' => $response['data']['transactionId'],
        );
    }

    public function nonce () {
        return $this->milliseconds ();
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $request = $this->implode_params($path, $params);
        $query = $this->omit ($params, $this->extract_params($path));
        $url = $this->urls['api'] . '/' . $this->version . '/' . $request;
        if ($api === 'public') {
            if ($query)
                $url .= '?' . $this->urlencode ($query);
        } else {
            $this->check_required_credentials();
            $nonce = $this->nonce ();
            $body = $this->urlencode (array_merge (array ( 'nonce' => $nonce ), $query));
            $secret = base64_decode ($this->secret);
            $auth = $request . '\0' . $body;
            $signature = $this->hmac ($this->encode ($auth), $secret, 'sha512', 'base64');
            $headers = array (
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Rest-Key' => $this->apiKey,
                'Rest-Sign' => $this->decode ($signature),
            );
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function request ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $response = $this->fetch2 ($path, $api, $method, $params, $headers, $body);
        if (is_array ($response) && array_key_exists ('result', $response))
            if ($response['result'] === 'success')
                return $response;
        throw new ExchangeError ($this->id . ' ' . $this->json ($response));
    }
}
