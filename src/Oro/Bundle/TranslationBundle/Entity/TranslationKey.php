<?php

namespace Oro\Bundle\TranslationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * "utf8mb4_bin" required for mysql to support case-sensitive values for "key" column
 *
 * @ORM\Entity(repositoryClass="Oro\Bundle\TranslationBundle\Entity\Repository\TranslationKeyRepository")
 * @ORM\Table(name="oro_translation_key", options={"collate"="utf8mb4_bin", "charset"="utf8mb4"}, uniqueConstraints={
 *      @ORM\UniqueConstraint(name="key_domain_uniq", columns={"key", "domain"})
 * })
 */
class TranslationKey
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="`key`", type="string", length=255, nullable=false)
     */
    protected $key;

    /**
     * @ORM\Column(type="string", length=255, nullable=false, options={"default"="messages"})
     */
    protected $domain;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $key
     * @return $this
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param mixed $domain
     * @return $this
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDomain()
    {
        return $this->domain;
    }
}
