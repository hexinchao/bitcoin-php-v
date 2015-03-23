<?php


namespace BitWasp\Bitcoin\Serializer\Key\HierarchicalKey;

use BitWasp\Bitcoin\Crypto\EcAdapter\EcAdapterInterface;
use BitWasp\Bitcoin\Exceptions\ParserOutOfRange;
use BitWasp\Bitcoin\Key\PrivateKeyFactory;
use BitWasp\Bitcoin\Key\PublicKeyFactory;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Network\NetworkInterface;
use BitWasp\Bitcoin\Parser;
use BitWasp\Bitcoin\Key\HierarchicalKey;

class HexExtendedKeySerializer
{
    /**
     * @var NetworkInterface
     */
    private $network;

    /**
     * @var EcAdapterInterface
     */
    private $ecAdapter;

    /**
     * @param EcAdapterInterface $ecAdapter
     * @param NetworkInterface $network
     * @throws \Exception
     */
    public function __construct(EcAdapterInterface $ecAdapter, NetworkInterface $network)
    {
        try {
            $network->getHDPrivByte();
            $network->getHDPubByte();
        } catch (\Exception $e) {
            throw new \Exception('Network not configured for HD wallets');
        }

        $this->network = $network;
        $this->ecAdapter = $ecAdapter;
    }

    /**
     * @return Math
     */
    public function getEcAdapter()
    {
        return $this->ecAdapter;
    }

    /**
     * @return NetworkInterface
     */
    public function getNetwork()
    {
        return $this->network;
    }

    /**
     * @param HierarchicalKey $key
     * @return \BitWasp\Bitcoin\Buffer
     */
    public function serialize(HierarchicalKey $key)
    {
        list ($prefix, $data) = ($key->isPrivate())
            ? [$this->network->getHDPrivByte(), '00' . $key->getPrivateKey()->getBuffer()]
            : [$this->network->getHDPubByte(), $key->getPublicKey()->getBuffer()];

        $bytes = new Parser();
        $bytes
            ->writeBytes(4, $prefix)
            ->writeInt(1, $key->getDepth())
            ->writeInt(4, $key->getFingerprint())
            ->writeInt(4, $key->getSequence())
            ->writeInt(32, $key->getChainCode())
            ->writeBytes(33, $data);

        $hex = $bytes
            ->getBuffer();

        return $hex;
    }

    /**
     * @param Parser $parser
     * @return HierarchicalKey
     * @throws ParserOutOfRange
     */
    public function fromParser(Parser & $parser)
    {
        try {
            list ($bytes, $depth, $parentFingerprint, $sequence, $chainCode, $keyData) =
                [
                    $parser->readBytes(4)->serialize('hex'),
                    $parser->readBytes(1)->serialize('int'),
                    $parser->readBytes(4)->serialize('int'),
                    $parser->readBytes(4)->serialize('int'),
                    $parser->readBytes(32)->serialize('int'),
                    $parser->readBytes(33)
                ];
        } catch (ParserOutOfRange $e) {
            throw new ParserOutOfRange('Failed to extract HierarchicalKey from parser');
        }

        $key = ($this->network->getHDPrivByte() == $bytes)
            ? PrivateKeyFactory::fromHex(substr($keyData, 2), true, $this->ecAdapter)
            : PublicKeyFactory::fromHex($keyData, $this->ecAdapter);

        $hd = new HierarchicalKey($this->ecAdapter, $depth, $parentFingerprint, $sequence, $chainCode, $key);

        return $hd;
    }

    /**
     * @param string $hex
     * @return HierarchicalKey
     * @throws ParserOutOfRange
     * @throws \Exception
     */
    public function parse($hex)
    {
        if (strlen($hex) !== 156) {
            throw new \Exception('Invalid extended key');
        }

        $parser = new Parser($hex);
        $hd = $this->fromParser($parser);
        return $hd;
    }
}
