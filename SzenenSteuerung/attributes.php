<?php

declare(strict_types=1);

if (defined('PHPUNIT_TESTSUITE')) {
    trait Attributes
    {
        public function SetAttribute(string $name, string $value)
        {
            $this->WriteAttributeString($name, $value);
        }

        public function GetAttribute(string $name)
        {
            return $this->ReadAttributeString($name);
        }
    }
} else {
    trait Attributes
    {
    }
}