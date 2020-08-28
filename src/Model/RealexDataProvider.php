<?php
/**
 * Copyright © ASMWS, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace asm\globalpayments\HPP\Model;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\QuoteGraphQl\Model\Cart\Payment\AdditionalDataProviderInterface;

/**
 * Format Globalpayments input into value expected when setting payment method
 */
class GlobalpaymentsDataProvider implements AdditionalDataProviderInterface
{
    const PATH_ADDITIONAL_DATA = 'realex';

    public function __construct() {}

    /**
     * Format Globalpayments input into value expected when setting payment method
     *
     * @param array $args
     * @return array
     * @throws GraphQlInputException
     */
    public function getData(array $args): array
    {
        if (!isset($args[self::PATH_ADDITIONAL_DATA])) {
            throw new GraphQlInputException(
                __('Required parameter "realex" for "payment_method" is missing.')
            );
        }

        return $args[self::PATH_ADDITIONAL_DATA];
    }
}

