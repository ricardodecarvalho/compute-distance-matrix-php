<?php

namespace DistanceMatrix;

class Compute
{
    private $apiKey;
    private $buildDistanceType;

    public function __construct($buildDistanceType = 'duration')
    {
        $this->apiKey = 'YOUR_GOOGLE_MAPS_API_KEY';
        $this->buildDistanceType = $buildDistanceType; // distance or duration
    }

    public function main($addresses)
    {
        return $this->createDistanceMatrix($this->createData($addresses));
    }

    private function createData($addresses)
    {
        return [
            'addresses' => $addresses
        ];
    }

    private function createDistanceMatrix($data)
    {
        $addresses = $data["addresses"];

        # Distance Matrix API only accepts 100 elements per request, so get rows in multiple requests.
        $maxElements = 100;
        $numAddresses = count($addresses);

        # Maximum number of rows that can be computed per request
        $maxRows = floor($maxElements / $numAddresses);

        $r = round($numAddresses % $maxRows);
        $q = round(($numAddresses - $r) / $maxRows);

        $destAddresses = $addresses;
        $distanceMatrix = [];

        # Send q requests, returning max_rows rows per request.
        for ($i = 0; $i < $q; $i++) {
            $originAddresses = array_slice($addresses, $i * $maxRows, $maxRows);
            $response = $this->sendRequest($originAddresses, $destAddresses);
            $distanceMatrix = array_merge($distanceMatrix, $this->buildDistanceMatrix($response));
        }

        # Get the remaining remaining r rows, if necessary.
        if ($r > 0) {
            $origin_addresses = array_slice($addresses, $q * $maxRows, $q * $maxRows + $r);
            $response = $this->sendRequest($origin_addresses, $destAddresses);
            $distanceMatrix = array_merge($distanceMatrix, $this->buildDistanceMatrix($response));
        }

        return $distanceMatrix;
    }

    # Build and send request for the given origin and destination addresses
    private function sendRequest($originAddresses, $destAddresses)
    {
        $request = 'https://maps.googleapis.com/maps/api/distancematrix/json?';
        $originAddressStr = $this->buildAddressStr($originAddresses);
        $destAddressStr = $this->buildAddressStr($destAddresses);

        $request = $request
            . '&origins=' . urlencode($originAddressStr)
            . '&destinations=' . urlencode($destAddressStr)
            . '&key=' . $this->apiKey;

        //$jsonResult = file_get_contents(__DIR__ . '/matrix.json');
        $jsonResult = $this->callCurl($request);

        return json_decode($jsonResult, true);
    }

    private function buildAddressStr($addresses)
    {
        # Build a pipe-separated string of addresses
        $addressStr = '';
        for ($i = 0; $i < count($addresses) - 1; $i++) {
            $addressStr .= $addresses[$i] . '|';
        }
        $addressStr .= end($addresses);
        return $addressStr;
    }

    private function buildDistanceMatrix($response)
    {
        $distanceMatrix = [];
        foreach ($response['rows'] as $row) {
            $rowList = [];
            for ($i = 0; $i < count($row['elements']); $i++) {
                if ($row['elements'][$i]['status'] !== 'OK') {
                    error_log("The origin and/or destination of this pairing could not be geocoded: "
                        . json_encode($response));
                    throw new \InvalidArgumentException(
                        'Um ou mais endereços não puderam ser geocodificados.',
                        400
                    );
                }
                array_push($rowList, $row['elements'][$i][$this->buildDistanceType]['value']);
            }
            array_push($distanceMatrix, $rowList);
        }
        return $distanceMatrix;
    }

    // todo: Change to Guzzle PHP http://docs.guzzlephp.org/en/stable/
    private function callCurl($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }
}
