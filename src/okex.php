<?php

namespace ccxt;

class okex extends okcoinusd {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'okex',
            'name' => 'OKEX',
            'countries' => array ( 'CN', 'US' ),
            'has' => array (
                'CORS' => false,
                'futures' => true,
                'hasFetchTickers' => true,
                'fetchTickers' => true,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/32552768-0d6dd3c6-c4a6-11e7-90f8-c043b64756a7.jpg',
                'api' => array (
                    'web' => 'https://www.okex.com/v2',
                    'public' => 'https://www.okex.com/api',
                    'private' => 'https://www.okex.com/api',
                ),
                'www' => 'https://www.okex.com',
                'doc' => 'https://www.okex.com/rest_getStarted.html',
                'fees' => 'https://www.okex.com/fees.html',
            ),
        ));
    }

    public function common_currency_code ($currency) {
        $currencies = array (
            'FAIR' => 'FairGame',
            'YOYO' => 'YOYOW',
            'NANO' => 'XRB',
        );
        if (is_array ($currencies) && array_key_exists ($currency, $currencies))
            return $currencies[$currency];
        return $currency;
    }

    public function calculate_fee ($symbol, $type, $side, $amount, $price, $takerOrMaker = 'taker', $params = array ()) {
        $market = $this->markets[$symbol];
        $key = 'quote';
        $rate = $market[$takerOrMaker];
        $cost = floatval ($this->cost_to_precision($symbol, $amount * $rate));
        if ($side === 'sell') {
            $cost *= $price;
        } else {
            $key = 'base';
        }
        return array (
            'type' => $takerOrMaker,
            'currency' => $market[$key],
            'rate' => $rate,
            'cost' => floatval ($this->fee_to_precision($symbol, $cost)),
        );
    }

    public function fetch_markets () {
        $markets = parent::fetch_markets();
        // TODO => they have a new fee schedule as of Feb 7
        // the new fees are progressive and depend on 30-day traded volume
        // the following is the worst case
        for ($i = 0; $i < count ($markets); $i++) {
            if ($markets[$i]['spot']) {
                $markets[$i]['maker'] = 0.0015;
                $markets[$i]['taker'] = 0.0020;
            } else {
                $markets[$i]['maker'] = 0.0003;
                $markets[$i]['taker'] = 0.0005;
            }
        }
        return $markets;
    }

    public function fetch_tickers ($symbols = null, $params = array ()) {
        $this->load_markets();
        $request = array ();
        $response = $this->publicGetTickers (array_merge ($request, $params));
        $tickers = $response['tickers'];
        $timestamp = intval ($response['date']) * 1000;
        $result = array ();
        for ($i = 0; $i < count ($tickers); $i++) {
            $ticker = $tickers[$i];
            $market = null;
            if (is_array ($ticker) && array_key_exists ('symbol', $ticker)) {
                $marketId = $ticker['symbol'];
                if (is_array ($this->markets_by_id) && array_key_exists ($marketId, $this->markets_by_id))
                    $market = $this->markets_by_id[$marketId];
            }
            $ticker = $this->parse_ticker(array_merge ($tickers[$i], array ( 'timestamp' => $timestamp )), $market);
            $symbol = $ticker['symbol'];
            $result[$symbol] = $ticker;
        }
        return $result;
    }
}
