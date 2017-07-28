<?php

namespace MageHost\PerformanceDashboard\Model\DashboardRow;

class ConfigSetting extends \Magento\Framework\DataObject implements \MageHost\PerformanceDashboard\Model\DashboardRowInterface
{
    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $_scopeConfig;

    /** @var \Magento\Store\Model\StoreManagerInterface */
    protected $_storeManager;

    /**
     * Constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param array $data -- expects keys 'title', 'path' and 'recommended' to be set
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        parent::__construct($data);

        $info = [];
        $action = [];
        $defaultResult = $this->checkConfigSetting($this->getPath(), $this->getRecommended());
        $status = $defaultResult->getStatus();
        if (0 < $defaultResult->getStatus()) {
            $info[] = $defaultResult->getInfo();
            $action[] = $defaultResult->getAction();
        }

        /** @var \Magento\Store\Api\Data\WebsiteInterface $website */
        foreach ($this->_storeManager->getWebsites() as $website) {
            $websiteResult = $this->checkConfigSetting($this->getPath(), $this->getRecommended(), $website);
            if ($websiteResult->getStatus() > $defaultResult->getStatus()) {
                $status = $websiteResult->getStatus();
                $info[] = $websiteResult->getInfo();
                $action[] = $websiteResult->getAction();
            }
            foreach ($this->_storeManager->getStores() as $store) {
                if ($store->getWebsiteId() == $website->getId()) {
                    $storeResult = $this->checkConfigSetting($this->getPath(), $this->getRecommended(), $store);
                    if ($storeResult->getStatus() > $websiteResult->getStatus()) {
                        $status = $storeResult->getStatus();
                        $info[] = $storeResult->getInfo();
                        $action[] = $storeResult->getAction();
                    }
                }
            }
        }

        if (0 == $status) {
            $this->setInfo($defaultResult->getInfo());
        } else {
            $this->setInfo(implode("\n", $info));
            $this->setAction(implode("\n", $action));
        }
        $this->setStatus($status);
    }

    /**
     * Check a config setting for a specific scope
     *
     * @param string $path
     * @param mixed $recommended
     * @param string|null $scope -- null = default scope
     * @return \Magento\Framework\DataObject
     */
    protected function checkConfigSetting(
        $path,
        $recommended,
        $scope = null
    ) {
    
        $result = new \Magento\Framework\DataObject;

        if (is_null($scope)) {
            $scopeType = \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $scopeCode = null;
            $showScope = __('in Default Config');
        } elseif ($scope instanceof \Magento\Store\Api\Data\WebsiteInterface) {
            $scopeType = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE;
            $scopeCode = $scope->getCode();
            $showScope = sprintf(__("for website '%s'"), $scope->getName());
        } elseif ($scope instanceof \Magento\Store\Api\Data\StoreInterface) {
            $scopeType = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $scopeCode = $scope->getCode();
            $showScope = sprintf(__("for store '%s'"), $scope->getName());
        } else {
            $result->setStatus(3);
            $result->setInfo(sprintf(__("Unknown scope")));
            return $result;
        }

        $result->setValue($this->_scopeConfig->getValue($path, $scopeType, $scopeCode));

        $result->setInfo(sprintf(
            __("%s %s"),
            ucfirst($this->getShowValue($result->getValue(), gettype($recommended))),
            $showScope
        ));
        if ($recommended == $result->getValue()) {
            $result->setStatus(0);
        } else {
            $result->setStatus(2);
            $result->setAction(sprintf(
                __("Switch to %s %s"),
                ucfirst($this->getShowValue($recommended, gettype($recommended))),
                $showScope
            ));
        }

        return $result;
    }

    /**
     * Format a value to show in frontend
     *
     * @param mixed $value
     * @param string $type
     * @return \Magento\Framework\Phrase|string
     */
    protected function getShowValue($value, $type = 'string')
    {
        if ('boolean' == $type) {
            $showValue = $value ? __('enabled') : __('disabled');
        } elseif ('string' == $type) {
            $showValue = $value;
        } else {
            $showValue = sprintf(__("Unsupported type: '%s'"), $type);
        }
        return $showValue;
    }
}
