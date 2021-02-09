<?php
namespace Sale\Handlers\PaySystem;

use Bitrix\Main,
	Bitrix\Main\Web\HttpClient,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale,
	Bitrix\Sale\PaySystem,
	Bitrix\Main\Request,
	Bitrix\Sale\Payment,
	Bitrix\Sale\PaySystem\ServiceResult,
	Bitrix\Sale\PaymentCollection,
	Bitrix\Sale\PriceMaths;

Loc::loadMessages(__FILE__);

/**
 * Class BePaidHandler
 * @package Sale\Handlers\PaySystem
 */
class BeGatewayEripHandler extends PaySystem\ServiceHandler
{
	private const API_URL                = 'https://api.bepaid.by';

	private const TRACKING_ID_DELIMITER  = '#';

	private const STATUS_SUCCESSFUL_CODE = 'successful';
	private const STATUS_ERROR_CODE      = 'error';

	private const SEND_METHOD_HTTP_POST  = 'POST';
	private const SEND_METHOD_HTTP_GET   = 'GET';

	/**
	 * @param Payment $payment
	 * @param Request|null $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	public function initiatePay(Payment $payment, Request $request = null): ServiceResult
	{
		$result = new ServiceResult();

    if ($this->isAutoMode($payment)) {
  		$createEripBillResult = $this->createEripBill($payment);
  		if (!$createPaymentTokenResult->isSuccess())
  		{
  			$result->addErrors($createEripBillResult->getErrors());
  			return $result;
  		}

  		$createEripBillData = $createEripBillResult->getData();
  		if (!empty($createEripBillResult['transaction']['uid']))
  		{
  			$result->setPsData(['PS_INVOICE_ID' => $createEripBillResult['transaction']['uid']]);
  		}

  		$this->setExtraParams($this->getTemplateParams($payment, $createEripBillData));

  		$showTemplateResult = $this->showTemplate($payment, $this->getTemplateName($payment));
  		if ($showTemplateResult->isSuccess())
  		{
  			$result->setTemplate($showTemplateResult->getTemplate());
  		}
  		else
  		{
  			$result->addErrors($showTemplateResult->getErrors());
  		}
    }

		return $result;
	}

	/**
	 * @param Payment $payment
	 * @return string
	 */
	private function getTemplateName(Payment $payment): string
	{
    return $this->isAutoMode($payment) ? 'auto' : 'manual';
	}

	/**
	 * @param Payment $payment
	 * @return boolean
	 */
  private function isAutoMode(Payment $payment)
  {
    return $this->getBusinessValue($payment, 'BEGATEWAY_ERIP_AUTO_BILL') == 'Y'
  }

	/**
	 * @param Payment $payment
	 * @param array $paymentTokenData
	 * @return array
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getTemplateParams(Payment $payment, array $eripBillData): array
  {
		$params = [
			'sum' => PriceMaths::roundPrecision($payment->getSum()),
			'currency' => $payment->getField('CURRENCY'),
      'instruction' => $eripBillData['transaction']['erip']['instruction'],
      'qr_code' => $eripBillData['transaction']['erip']['qr_code'],
      'account_number' => $eripBillData['transaction']['erip']['account_number']
		];

		return $params;
	}

	/**
	 * @param Payment $payment
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function createEripBill(Payment $payment): ServiceResult
  {
		$result = new ServiceResult();

		$url = $this->getUrl($payment, 'sendEripBill');
		$params = [
			'request' => [
				'test' => $this->isTestMode($payment),
				'amount' => $payment->getSum() * 100,
				'currency' => $payment->getField('CURRENCY'),
				'description' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getPaymentDescription($payment), 255),
				'tracking_id' => $payment->getId().self::TRACKING_ID_DELIMITER.$this->service->getField('ID'),
				'notification_url' => $this->getBusinessValue($payment, 'BEGATEWAY_ERIP_NOTIFICATION_URL'),
				'language' => LANGUAGE_ID,
        'email' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_EMAIL')),
        'customer' => [
          'first_name' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_NAME_FIRST')),
          'middle_name' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_NAME_MIDDLE')),
          'last_name' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_NAME_LAST')),
          'country' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_COUNTRY')),
          'city' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_CITY')),
          'zip' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_ZIP')),
          'address' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_ADDRESS')),
          'phone' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_PHONE')),
        ],
        'payment_method' => [
          'type' => 'erip',
          'account_number' => \BeGateway\Module\Erip\Encoder::toUtf8(
            $this->getAccountDescription($payment)
          ),
          'service_info' => \BeGateway\Module\Erip\Encoder::str_split(
            \BeGateway\Module\Erip\Encoder::toUtf8($this->getPaymentDescription($payment))
          ),
          'receipt' => \BeGateway\Module\Erip\Encoder::str_split(
            \BeGateway\Module\Erip\Encoder::toUtf8($this->getReceiptDescription($payment))
          )
        ],
        'additional_data' => [
          'meta' => [
            'cms' => [
              'name' => '1C-Bitrix',
              'version' => ModuleManager::getVersion('main'),
              'module_id' =>  ModuleManager::getVersion('begateway.erip')
            ]
          ]
        ]
			]
		];

    $service_code = $this->getBusinessValue($payment, 'BEGATEWAY_ERIP_SERVICE_CODE');
    if (isset($service_code) && !empty(trim($service_code))) {
      $params['request']['payment_method']['service_no'] = $service_code;
    }

		$headers = $this->getHeaders($payment);

		$sendResult = $this->send(self::SEND_METHOD_HTTP_POST, $url, $params, $headers);
		if ($sendResult->isSuccess())
		{
			$eripBillData = $sendResult->getData();
			$verifyResponseResult = $this->verifyResponse($eripBillData);
			if ($verifyResponseResult->isSuccess())
			{
				$result->setData($eripBillData);
			}
			else
			{
				$result->addErrors($verifyResponseResult->getErrors());
			}
		}
		else
		{
			$result->addErrors($sendResult->getErrors());
		}

		return $result;
	}

	/**
	 * @param Payment $payment
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function getBeGatewayEripPayment(Payment $payment): ServiceResult
	{
		$result = new ServiceResult();

		$url = $this->getUrl($payment, 'getPaymentStatus');
		$headers = $this->getHeaders($payment);

		$sendResult = $this->send(self::SEND_METHOD_HTTP_GET, $url, [], $headers);
		if ($sendResult->isSuccess())
		{
			$paymentData = $sendResult->getData();
			$verifyResponseResult = $this->verifyResponse($paymentData);
			if ($verifyResponseResult->isSuccess())
			{
				$result->setData($paymentData);
			}
			else
			{
				$result->addErrors($verifyResponseResult->getErrors());
			}
		}
		else
		{
			$result->addErrors($sendResult->getErrors());
		}

		return $result;
	}

	/**
	 * @param string $method
	 * @param string $url
	 * @param array $params
	 * @param array $headers
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function send(string $method, string $url, array $params = [], array $headers = []): ServiceResult
	{
		$result = new ServiceResult();

		$httpClient = new HttpClient();
		foreach ($headers as $name => $value)
		{
			$httpClient->setHeader($name, $value);
		}

		if ($method === self::SEND_METHOD_HTTP_GET)
		{
			$response = $httpClient->get($url);
		}
		else
		{
			$postData = null;
			if ($params)
			{
				$postData = static::encode($params);
			}

			PaySystem\Logger::addDebugInfo(__CLASS__.': request data: '.$postData);

			$response = $httpClient->post($url, $postData);
		}

		if ($response === false)
		{
			$errors = $httpClient->getError();
			foreach ($errors as $code => $message)
			{
				$result->addError(PaySystem\Error::create($message, $code));
			}

			return $result;
		}

		PaySystem\Logger::addDebugInfo(__CLASS__.': response data: '.$response);

		$response = static::decode($response);
		if ($response)
		{
			$result->setData($response);
		}
		else
		{
			$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_RESPONSE_DECODE_ERROR')));
		}

		return $result;
	}

	/**
	 * @param array $response
	 * @return ServiceResult
	 */
	private function verifyResponse(array $response): ServiceResult
	{
		$result = new ServiceResult();

		if (!empty($response['errors']))
		{
			$result->addError(PaySystem\Error::create($response['message']));
		}

		return $result;
	}

	/**
	 * @return array|string[]
	 */
	public function getCurrencyList(): array
	{
		return ['BYN'];
	}

	/**
	 * @param Payment $payment
	 * @param Request $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	public function processRequest(Payment $payment, Request $request): ServiceResult
	{
		$result = new ServiceResult();

		$inputStream = static::readFromStream();
		$data = static::decode($inputStream);
		$transaction = $data['transaction'];

		$beGatewayEripPaymentResult = $this->getBeGatewayEripPayment($payment);
		if ($beGatewayEripPaymentResult->isSuccess())
		{
			$beGatewayEripPaymentData = $beGatewayEripPaymentResult->getData();
			if ($beGatewayEripPaymentData['transaction']['status'] === self::STATUS_SUCCESSFUL_CODE)
			{
				$description = Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_TRANSACTION', [
					'#ID#' => $transaction['uid'],
				]);
				$fields = [
					'PS_STATUS_CODE' => $transaction['status'],
					'PS_STATUS_DESCRIPTION' => $description,
					'PS_SUM' => $transaction['amount'] / 100,
					'PS_STATUS' => 'N',
					'PS_CURRENCY' => $transaction['currency'],
					'PS_RESPONSE_DATE' => new Main\Type\DateTime()
				];

				if ($this->isSumCorrect($payment, $transaction['amount'] / 100))
				{
					$fields['PS_STATUS'] = 'Y';

					PaySystem\Logger::addDebugInfo(
						__CLASS__.': PS_CHANGE_STATUS_PAY='.$this->getBusinessValue($payment, 'PS_CHANGE_STATUS_PAY')
					);

					if ($this->getBusinessValue($payment, 'PS_CHANGE_STATUS_PAY') === 'Y')
					{
						$result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
					}
				}
				else
				{
					$error = Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_ERROR_SUM');
					$fields['PS_STATUS_DESCRIPTION'] .= '. '.$error;
					$result->addError(PaySystem\Error::create($error));
				}

				$result->setPsData($fields);
			}
			else
			{
				$result->addError(
					PaySystem\Error::create(
						Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_ERROR_STATUS',
							[
								'#STATUS#' => $transaction['status'],
							]
						)
					)
				);
			}
		}
		else
		{
			$result->addErrors($beGatewayEripPaymentResult->getErrors());
		}

		return $result;
	}

	/**
	 * @param Payment $payment
	 * @param $sum
	 * @return bool
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function isSumCorrect(Payment $payment, $sum): bool
	{
		PaySystem\Logger::addDebugInfo(
			__CLASS__.': bePaidSum='.PriceMaths::roundPrecision($sum)."; paymentSum=".PriceMaths::roundPrecision($payment->getSum())
		);

		return PriceMaths::roundPrecision($sum) === PriceMaths::roundPrecision($payment->getSum());
	}

	/**
	 * @param Request $request
	 * @param int $paySystemId
	 * @return bool
	 */
	public static function isMyResponse(Request $request, $paySystemId): bool
	{
		$inputStream = static::readFromStream();
		if ($inputStream)
		{
			$data = static::decode($inputStream);
			if ($data === false)
			{
				return false;
			}

			if (isset($data['transaction']['tracking_id']))
			{
				[, $trackingPaySystemId] = explode(self::TRACKING_ID_DELIMITER, $data['transaction']['tracking_id']);
				return (int)$trackingPaySystemId === (int)$paySystemId;
			}
		}

		return false;
	}

	/**
	 * @param Request $request
	 * @return bool|int|mixed
	 */
	public function getPaymentIdFromRequest(Request $request)
	{
		$inputStream = static::readFromStream();
		if ($inputStream)
		{
			$data = static::decode($inputStream);
			if (isset($data['transaction']['tracking_id']))
			{
				[$trackingPaymentId] = explode(self::TRACKING_ID_DELIMITER, $data['transaction']['tracking_id']);
				return (int)$trackingPaymentId;
			}
		}

		return false;
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getPaymentDescription(Payment $payment)
	{
		return $this->setDescriptionPlaceholders('BEGATEWAY_ERIP_PAYMENT_DESCRIPTION', $payment);
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getReceiptDescription(Payment $payment)
	{
		return $this->setDescriptionPlaceholders('BEGATEWAY_ERIP_RECEIPT_PAYMENT_DESCRIPTION', $payment);
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getAccountDescription(Payment $payment)
	{
		return $this->setDescriptionPlaceholders('BEGATEWAY_ERIP_PAYMENT_ACCOUNT', $payment);
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function setDescriptionPlaceholders(string $description, Payment $payment)
	{
		/** @var PaymentCollection $collection */
		$collection = $payment->getCollection();
		$order = $collection->getOrder();
		$userEmail = $order->getPropertyCollection()->getUserEmail();

		$processed_description =  str_replace(
			[
				'#PAYMENT_NUMBER#',
				'#ORDER_NUMBER#',
				'#PAYMENT_ID#',
				'#ORDER_ID#',
				'#USER_EMAIL#'
			],
			[
				$payment->getField('ACCOUNT_NUMBER'),
				$order->getField('ACCOUNT_NUMBER'),
				$payment->getId(),
				$order->getId(),
				($userEmail) ? $userEmail->getValue() : ''
			],
			$this->getBusinessValue($payment, $description)
		);

		return $processed_description;
	}

	/**
	 * @param Payment $payment
	 * @return array
	 */
	private function getHeaders(Payment $payment): array
	{
		$headers = [
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
			'Authorization' => 'Basic '.$this->getBasicAuthString($payment),
			'RequestID' => $this->getIdempotenceKey(),
		];

		return $headers;
	}

	/**
	 * @param Payment $payment
	 * @return string
	 */
	private function getBasicAuthString(Payment $payment): string
	{
		return base64_encode(
			$this->getBusinessValue($payment, 'BEGATEWAY_ERIP_ID')
			. ':'
			. $this->getBusinessValue($payment, 'BEGATEWAY_ERIP_SECRET_KEY')
		);
	}

	/**
	 * @return string
	 */
	private function getIdempotenceKey(): string
	{
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	/**
	 * @param Payment $payment
	 * @param string $action
	 * @return string
	 */
	protected function getUrl(Payment $payment = null, $action): string
	{
		$url = parent::getUrl($payment, $action);
		if ($payment !== null && $action === 'getPaymentStatus')
		{
			$url = str_replace('#uid#', $payment->getField('PS_INVOICE_ID'), $url);
		}

		return $url;
	}

	/**
	 * @return array
	 */
	protected function getUrlList(): array
	{
		return [
			'sendEripBill' => self::API_URL.'/beyag/payments',
      'getEripBillStatus' => self:API_URL.'/beyag/payments/#uid#'
		];
	}

	/**
	 * @param Payment $payment
	 * @return bool
	 */
	protected function isTestMode(Payment $payment = null): bool
	{
		return ($this->getBusinessValue($payment, 'PS_IS_TEST') === 'Y');
	}

	/**
	 * @return bool|string
	 */
	private static function readFromStream()
	{
		return file_get_contents('php://input');
	}

	/**
	 * @param array $data
	 * @return mixed
	 * @throws Main\ArgumentException
	 */
	private static function encode(array $data)
	{
		return Main\Web\Json::encode($data, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * @param string $data
	 * @return mixed
	 */
	private static function decode($data)
	{
		try
		{
			return Main\Web\Json::decode($data);
		}
		catch (Main\ArgumentException $exception)
		{
			return false;
		}
	}
}