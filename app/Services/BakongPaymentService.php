<?php

namespace App\Services;

use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\MerchantInfo;
use KHQR\Exceptions\KHQRException;
use Illuminate\Support\Facades\Log;

class BakongPaymentService
{
    private $bakongKhqr;
    private $bakongAccountId;
    private $merchantName;
    private $merchantCity;
    private $mobileNumber;

    public function __construct()
    {
        $token = config('services.bakong.api_token');
        $this->bakongAccountId = config('services.bakong.account_id');
        $this->merchantName = config('services.bakong.merchant_name');
        $this->merchantCity = config('services.bakong.merchant_city', 'Phnom Penh');
        $this->mobileNumber = config('services.bakong.mobile_number');

        // Initialize BakongKHQR with token if available
        if ($token) {
            $this->bakongKhqr = new BakongKHQR($token);
        }
    }

    /**
     * Generate KHQR code for an order using manual TLV generation (proven working method)
     * 
     * @param float $amount
     * @param string $currency (USD or KHR)
     * @param string|null $billNumber
     * @param string|null $storeLabel
     * @return array
     */
    public function generateQRCode($amount, $currency = 'USD', $billNumber = null, $storeLabel = null)
    {
        try {
            // Ensure amount is properly rounded and validated
            $amount = round((float) $amount, 2);
            
            // Log input parameters
            Log::info('Bakong QR Generation Started', [
                'amount' => $amount,
                'currency' => $currency,
                'billNumber' => $billNumber,
                'storeLabel' => $storeLabel,
                'account_id' => $this->bakongAccountId,
                'merchant_name' => $this->merchantName
            ]);

            // Validate amount
            if ($amount <= 0) {
                Log::error('Invalid amount provided', ['amount' => $amount]);
                return [
                    'success' => false,
                    'message' => 'Amount must be greater than 0',
                    'error' => 'INVALID_AMOUNT'
                ];
            }

            if ($amount > 999999.99) {
                Log::error('Amount too large', ['amount' => $amount]);
                return [
                    'success' => false,
                    'message' => 'Amount exceeds maximum limit',
                    'error' => 'AMOUNT_TOO_LARGE'
                ];
            }

            // Validate required fields
            if (empty($this->bakongAccountId)) {
                Log::error('Bakong account ID is empty');
                return [
                    'success' => false,
                    'message' => 'Bakong account ID not configured',
                    'error' => 'BAKONG_ACCOUNT_ID is empty'
                ];
            }

            if (empty($this->merchantName)) {
                Log::error('Bakong merchant name is empty');
                return [
                    'success' => false,
                    'message' => 'Bakong merchant name not configured',
                    'error' => 'BAKONG_MERCHANT_NAME is empty'
                ];
            }

            // Generate KHQR string using manual TLV (proven working method)
            $khqrString = $this->generateKhqrString(
                $this->bakongAccountId,
                $this->merchantName,
                $this->merchantCity,
                $amount,
                $currency,
                $billNumber
            );

            // Calculate MD5 from the FULL KHQR string (including CRC)
            // This matches the official Bakong SDK: md5($khqr) on line 121 of BakongKHQR.php
            $md5 = md5($khqrString);

            Log::info('QR Generation Successful', [
                'qr_length' => strlen($khqrString),
                'md5' => $md5,
                'amount' => $amount,
                'currency' => $currency
            ]);

            return [
                'success' => true,
                'qr_string' => $khqrString,
                'md5' => $md5,
                'amount' => $amount,
                'currency' => $currency
            ];

        } catch (\Exception $e) {
            Log::error('Bakong QR Generation Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to generate QR code',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Helper to compute TLV (Tag-Length-Value)
     */
    private function generateTlv($tag, $value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        $valueStr = (string) $value;
        $length = str_pad((string) strlen($valueStr), 2, '0', STR_PAD_LEFT);
        return $tag . $length . $valueStr;
    }

    /**
     * Helper to compute CRC16 CCITT
     */
    private function calculateCrc16($data)
    {
        $crc = 0xFFFF;
        $jf = 0x1021;
        $length = strlen($data);
        
        for ($i = 0; $i < $length; $i++) {
            $b = ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                $bit = (($b >> (7 - $j)) & 1) == 1;
                $c15 = (($crc >> 15) & 1) == 1;
                $crc <<= 1;
                if ($c15 ^ $bit) {
                    $crc ^= $jf;
                }
            }
        }
        
        $crc &= 0xFFFF;
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Generate KHQR string using manual TLV generation (matching proven working standard)
     */
    private function generateKhqrString($bankAccount, $merchantName, $merchantCity, $amount, $currency, $billNumber)
    {
        $qr = "";
        
        // Tag 00: Payload Format Indicator
        $qr .= $this->generateTlv("00", "01");
        
        // Tag 01: Point of Initiation (12 = Dynamic QR)
        $qr .= $this->generateTlv("01", "12");
        
        // Tag 29: Individual Bakong Account
        $qr .= $this->generateTlv("29", $this->generateTlv("00", $bankAccount));
        
        // Tag 52: Merchant Category Code
        $qr .= $this->generateTlv("52", "5999");
        
        // Tag 53: Transaction Currency
        $qr .= $this->generateTlv("53", $currency === 'KHR' ? "116" : "840");
        
        // Tag 54: Transaction Amount
        if ($amount !== null && $amount !== '') {
            if ($currency === 'KHR') {
                $amountStr = (string) round((float)$amount);
            } else {
                $amountStr = number_format((float)$amount, 2, '.', '');
            }
            $qr .= $this->generateTlv("54", $amountStr);
        }
        
        // Tag 58: Country Code
        $qr .= $this->generateTlv("58", "KH");
        
        // Tag 59: Merchant Name
        $qr .= $this->generateTlv("59", $merchantName);
        
        // Tag 60: Merchant City
        $qr .= $this->generateTlv("60", $merchantCity ?: "Phnom Penh");
        
        // Tag 99: Timestamp
        $now = (int) round(microtime(true) * 1000);
        $expiry = $now + (86400000 * 1); // 1 day expiry
        $tag99inner = $this->generateTlv("00", (string)$now) . $this->generateTlv("01", (string)$expiry);
        $qr .= $this->generateTlv("99", $tag99inner);
        
        // Tag 62: Additional Data (Bill Number)
        if ($billNumber) {
            $qr .= $this->generateTlv("62", $this->generateTlv("01", $billNumber));
        }
        
        // Tag 63: CRC Checksum
        $qr .= "6304";
        $qr .= $this->calculateCrc16($qr);
        
        return $qr;
    }

    /**
     * Check if Bakong account exists
     * 
     * @param string $accountId
     * @return bool
     */
    public function checkAccountExists($accountId)
    {
        try {
            $response = BakongKHQR::checkBakongAccount($accountId);
            if ($response->status['code'] == 0) {
                return $response->data['bakongAccountExists'];
            }
            
            return false;
        } catch (KHQRException $e) {
            Log::error('Bakong Account Check Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check transaction status by MD5
     * 
     * @param string $md5Hash
     * @param bool $isTest
     * @return array|null
     */
    public function checkTransactionByMD5($md5Hash, $isTest = false)
    {
        try {
            if (!$this->bakongKhqr) {
                Log::error('Bakong API token not configured');
                throw new \Exception('Bakong API token not configured');
            }

            Log::info('Checking Bakong transaction', [
                'md5' => $md5Hash,
                'isTest' => $isTest
            ]);

            $response = $this->bakongKhqr->checkTransactionByMD5($md5Hash, $isTest);
            
            Log::info('Bakong transaction check response', [
                'response_type' => \gettype($response),
                'response' => $response
            ]);

            // Check for responseCode (new format) or status.code (old format)
            $isSuccess = false;
            if (isset($response['responseCode']) && $response['responseCode'] == 0) {
                $isSuccess = true;
            } elseif (isset($response['status']) && $response['status']['code'] == 0) {
                $isSuccess = true;
            }

            if ($isSuccess && isset($response['data'])) {
                Log::info('Transaction found!', ['data' => $response['data']]);
                
                // The transaction data is in response['data']
                // It contains: hash, fromAccountId, toAccountId, currency, amount, etc.
                // We need to mark it as COMPLETED since the transaction exists
                $transactionData = $response['data'];
                $transactionData['status'] = 'COMPLETED'; // Add status field
                
                return [
                    'success' => true,
                    'transaction' => $transactionData
                ];
            }

            Log::warning('Transaction not found or pending', [
                'responseCode' => $response['responseCode'] ?? 'N/A',
                'responseMessage' => $response['responseMessage'] ?? 'N/A'
            ]);

            return [
                'success' => false,
                'message' => 'Transaction not found'
            ];

        } catch (KHQRException $e) {
            Log::error('Bakong Transaction Check Error (KHQR Exception): ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Bakong Transaction Check Error (General Exception): ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify KHQR string
     * 
     * @param string $qrString
     * @return bool
     */
    public function verifyQRCode($qrString)
    {
        try {
            $result = BakongKHQR::verify($qrString);
            
            Log::info('QR Code Verification Result', [
                'qr_length' => strlen($qrString),
                'is_valid' => $result->isValid,
                'result_type' => \gettype($result)
            ]);
            
            return $result->isValid;
        } catch (KHQRException $e) {
            Log::error('Bakong QR Verification Error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('Bakong QR Verification Error (General): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decode KHQR string
     * 
     * @param string $qrString
     * @return array|null
     */
    public function decodeQRCode($qrString)
    {
        try {
            $response = BakongKHQR::decode($qrString);
            
            if ($response->status['code'] == 0) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to decode QR code'
            ];

        } catch (KHQRException $e) {
            Log::error('Bakong QR Decode Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Renew Bakong API token
     * 
     * @param string $email
     * @return array
     */
    public function renewToken($email)
    {
        try {
            $result = BakongKHQR::renewToken($email);
            
            if ($result['responseCode'] == 0) {
                return [
                    'success' => true,
                    'token' => $result['data']['token'],
                    'message' => $result['responseMessage']
                ];
            }

            return [
                'success' => false,
                'message' => $result['responseMessage'],
                'error_code' => $result['errorCode']
            ];

        } catch (\Exception $e) {
            Log::error('Bakong Token Renewal Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
