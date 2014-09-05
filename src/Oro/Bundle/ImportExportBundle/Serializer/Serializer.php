<?php

namespace Oro\Bundle\ImportExportBundle\Serializer;

use Doctrine\Common\Collections\Collection;

use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Serializer as BaseSerializer;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Exception\LogicException;

use Oro\Bundle\ImportExportBundle\Serializer\Normalizer\DenormalizerInterface;
use Oro\Bundle\ImportExportBundle\Serializer\Normalizer\NormalizerInterface;

class Serializer extends BaseSerializer implements DenormalizerInterface, NormalizerInterface
{
    const PROCESSOR_ALIAS_KEY = 'processorAlias';

    /**
     * {@inheritdoc}
     */
    public function normalize($data, $format = null, array $context = array())
    {
        if (null === $data || is_scalar($data)) {
            return $data;
        } elseif (is_object($data) && $this->supportsNormalization($data, $format)) {
            $this->cleanCacheIfDataIsCollection($data, $format, $context);
            return $this->normalizeObject($data, $format, $context);
        } elseif ($data instanceof \Traversable) {
            $normalized = array();
            foreach ($data as $key => $val) {
                $normalized[$key] = $this->normalize($val, $format, $context);
            }

            return $normalized;
        } elseif (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = $this->normalize($val, $format, $context);
            }

            return $data;
        }

        throw new UnexpectedValueException(
            sprintf('An unexpected value could not be normalized: %s', var_export($data, true))
        );
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        if (!$this->normalizers) {
            throw new LogicException('You must register at least one normalizer to be able to denormalize objects.');
        }

        $cacheKey = $this->getCacheKey($type, $format, $context);

        if (isset($this->denormalizerCache[$cacheKey])) {
            return $this->denormalizerCache[$cacheKey]->denormalize($data, $type, $format, $context);
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof DenormalizerInterface
                && $normalizer->supportsDenormalization($data, $type, $format, $context)) {
                $this->denormalizerCache[$cacheKey] = $normalizer;

                return $normalizer->denormalize($data, $type, $format, $context);
            }
        }

        throw new UnexpectedValueException(
            sprintf('Could not denormalize object of type %s, no supporting normalizer found.', $type)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null, array $context = array())
    {
        try {
            $this->getNormalizer($data, $format, $context);
        } catch (RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = array())
    {
        try {
            $this->getDenormalizer($data, $type, $format, $context);
        } catch (RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $type
     * @param string $format
     * @param array  $context
     *
     * @return string
     */
    protected function getCacheKey($type, $format, array $context)
    {
        return md5($type . $format . $this->getProcessorAlias($context));
    }

    /**
     * @param array $context
     *
     * @return string
     */
    protected function getProcessorAlias(array $context)
    {
        return !empty($context[self::PROCESSOR_ALIAS_KEY])
            ? $context[self::PROCESSOR_ALIAS_KEY]
            : '';
    }

    /**
     * @param object $data
     * @param string $format
     * @param array $context
     */
    protected function cleanCacheIfDataIsCollection($data, $format, $context)
    {
        if ($data instanceof Collection) {
            $cacheKey = $this->getCacheKey(get_class($data), $format, $context);
            // Clear cache of normalizer for collections,
            // because of wrong behaviour when selecting normalizer for collections of elements with different types
            unset($this->normalizerCache[$cacheKey]);
        }
    }

    /**
     * {@inheritdoc}
     */
    private function normalizeObject($object, $format = null, array $context = array())
    {
        if (!$this->normalizers) {
            throw new LogicException('You must register at least one normalizer to be able to normalize objects.');
        }

        $class = get_class($object);

        $cacheKey = $this->getCacheKey($class, $format, $context);

        if (isset($this->normalizerCache[$cacheKey])) {
            return $this->normalizerCache[$cacheKey]->normalize($object, $format, $context);
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof NormalizerInterface
                && $normalizer->supportsNormalization($object, $format)) {
                $this->normalizerCache[$cacheKey] = $normalizer;

                return $normalizer->normalize($object, $format, $context);
            }
        }

        throw new UnexpectedValueException(
            sprintf('Could not normalize object of type %s, no supporting normalizer found.', $class)
        );
    }

    /**
     * {@inheritdoc}
     */
    private function getNormalizer($data, $format = null, array $context = array())
    {
        foreach ($this->normalizers as $normalizer) {
            if (!$normalizer instanceof NormalizerInterface) {
                continue;
            }

            /** @var NormalizerInterface $normalizer */
            $supportsNormalization = $normalizer->supportsNormalization($data, $format, $context);

            if ($supportsNormalization) {
                return $normalizer;
            }
        }

        throw new RuntimeException(sprintf('No normalizer found for format "%s".', $format));
    }

    /**
     * {@inheritdoc}
     */
    private function getDenormalizer($data, $type, $format = null, array $context = array())
    {
        foreach ($this->normalizers as $normalizer) {
            if (!$normalizer instanceof DenormalizerInterface) {
                continue;
            }

            /** @var DenormalizerInterface $normalizer */
            $supportsDenormalization = $normalizer->supportsDenormalization(
                $data,
                $type,
                $format,
                $context
            );

            if ($supportsDenormalization) {
                return $normalizer;
            }
        }

        throw new RuntimeException(sprintf('No denormalizer found for format "%s".', $format));
    }
}
