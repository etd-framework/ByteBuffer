<?php

namespace EtdSolutions\ByteBuffer;

/**
 * ByteBuffer
 */
class ByteBuffer extends AbstractBuffer {

    const DEFAULT_CAPACITY = null;
    const DEFAULT_FORMAT   = 'x';

    /**
     * @var \SplFixedArray|array
     */
    protected $buffer;

    /**
     * @var \SplFixedArray|array
     */
    protected $currentOffset = 0;

    /**
     * @var LengthMap
     */
    protected $lengthMap;

    public function __construct($capacity = self::DEFAULT_CAPACITY) {

        $this->lengthMap = new LengthMap();
        $this->initializeStructs($capacity);
    }

    public static function isBuffer($buffer) {

        return $buffer instanceof ByteBuffer;
    }

    /**
     * @param string|ByteBuffer|ByteBuffer[] $list
     * @param null     $totalLength
     *
     * @return ByteBuffer
     */
    public static function concat($list, $totalLength = null) {

        $list = (array) $list;

        if (!is_integer($totalLength)) {
            foreach ($list as $buffer) {
                if (self::isBuffer($buffer)) {
                    $totalLength += $buffer->length();
                } elseif (is_array($buffer)) {
                    $totalLength += sizeof($buffer);
                }
            }
        }

        $buf = new ByteBuffer($totalLength);

        $c = 0;
        foreach ($list as $buffer) {
            if (is_string($buffer)) {
                $buffer = str_split($buffer);
            } elseif (self::isBuffer($buffer)) {
                $buffer = $buffer->getBufferArray();
            }

            foreach ($buffer as $byte) {
                if ($c >= $totalLength) {
                    return $buf;
                }
                $buf->setByteRaw($byte);
                $c++;
            }
        }

        return $buf;

    }

    /**
     * @param string $string
     * @param string $encoding
     *
     * @return ByteBuffer
     */
    public static function from($string, $encoding = 'utf8') {

        $length        = strlen($string);
        $format        = 'a';
        $format_length = $length;

        if ($encoding == 'hex') {
            $format        = 'H';
            $format_length = '*';
        } elseif ($encoding == 'utf8') {
            $string = utf8_encode($string);
        } elseif ($encoding == 'ascii') {
            $string = mb_convert_encoding($string, "ASCII");
        }

        $buffer = new ByteBuffer($length);
        $buffer->write($string, 0, $format, $format_length);

        return $buffer;

    }

    /**
     * @param int $start
     * @param int $end
     *
     * @return ByteBuffer
     */
    public function slice($start = 0, $end = null) {

        if (!isset($end)) {
            $end = $this->length();
        }

        $new = new ByteBuffer($this->length());
        for ($i = $start; $i <= $end; $i++) {
            $new->setByteRaw($this->buffer[$i]);
        }

        return $new;

    }

    /**
     * @param ByteBuffer $target      A Buffer to copy into.
     * @param int        $targetStart The offset within target at which to begin copying to. Default: 0
     * @param int        $sourceStart The offset within buf at which to begin copying from. Default: 0
     * @param int        $sourceEnd   The offset within buf at which to stop copying (not inclusive). Default: buf.length
     *
     * @return int The number of bytes copied.
     */
    public function copy($target, $targetStart = 0, $sourceStart = 0, $sourceEnd = null) {

        if (!isset($sourceEnd)) {
            $sourceEnd = $this->length();
        }

        $c = 0;
        for ($i = $sourceStart, $j = $targetStart; $i < $sourceEnd; $i++, $j++, $c++) {
            $target->setByteRaw($this->buffer[$i], $j);
        }

        return $c;

    }

    public function toString($encoding = 'utf8', $start = 0, $end = null) {

        if (!is_int($end)) {
            $end = $this->length();
        }

        $format = 'a';

        if ($encoding == 'hex') {
            $format = 'H';
        }

        $data = $this->getBuffer($format, $start, $end);

        if ($encoding == 'utf8') {
            $data = utf8_decode($data);
        } elseif ($encoding == 'ascii') {
            $data = mb_convert_encoding($data, "ASCII");
        }

        return $data;
    }

    public function __toString() {

        return $this->toString();
    }

    public function setByteRaw($value, $offset = null) {

        if ($offset === null) {
            $offset = $this->currentOffset;
        }
        $this->buffer[$offset++] = $value;
        $this->currentOffset     = $offset;
    }

    public function getByteRaw($offset = null) {

        if ($offset === null) {
            $offset = $this->currentOffset;
        }
        if (!isset($this->buffer[$offset])) {
            return false;
        }

        return $this->buffer[$offset];
    }

    public function getBufferArray() {

        return $this->buffer;
    }

    public function getBuffer($format, $offset, $length) {

        $buf = '';
        foreach ($this->buffer as $index => $bytes) {
            if ($offset <= $index && $index < ($offset + $length)) {
                $buf .= unpack($format . '*', $bytes)[1];
            }
        }

        return $buf;
    }

    public function length() {

        return count($this->buffer);
    }

    public function setCurrentOffset($offset) {

        $this->currentOffset = $offset;
    }

    public function getCurrentOffset() {

        return $this->currentOffset;
    }

    public function write($string, $offset = null, $format = 'a', $length = null) {

        if (!isset($length)) {
            $length = strlen($string);
        }

        $this->insert($format . $length, $string, $offset, $length);
    }

    public function writeByte($byte, $offset = null) {

        if ($offset === null) {
            $offset = $this->currentOffset;
        }
        $this->buffer[$offset++] = $byte;
        $this->currentOffset     = $offset;
    }

    public function writeVStringLE($value, $offset = null) {

        if ($offset === null) {
            $offset = $this->currentOffset;
        }
        $bytes = unpack('C*', $value); //string to bytes in int
        $total = count($bytes);
        for ($i = 0; $i < $total; $i++) {
            $this->buffer[$offset++] = pack('H*', base_convert($bytes[$i + 1], 10, 16));
        }
        $this->currentOffset = $offset;
    }

    public function writeVHexStringBE($value, $offset = null) {

        if ($offset === null) {
            $offset = $this->currentOffset;
        }
        $symbols = str_split($value, 2); //string to bytes in int
        $total   = count($symbols);
        for ($i = 0; $i < $total; $i++) {
            $this->buffer[$offset++] = hex2bin($symbols[$i]);
        }
        $this->currentOffset = $offset;
    }

    public function writeVStringBE($value, $offset = null) {

        if ($offset === null) {
            $offset = $this->currentOffset;
        }
        $bytes = unpack('C*', $value); //string to bytes in int
        $total = count($bytes);
        for ($i = 0; $i < $total; $i++) {
            $this->buffer[$offset++] = pack('h*', base_convert($bytes[$i + 1], 10, 16));
        }
        $this->currentOffset = $offset;
    }

    public function writeInt8($value, $offset = null) {

        $format = 'C';
        $this->checkForOverSize(0xff, $value);
        $this->insert($format, $value, $offset, $this->lengthMap->getLengthFor($format));
    }

    public function writeInt16BE($value, $offset = null) {

        $format = 'n';
        $this->checkForOverSize(0xffff, $value);
        $this->insert($format, $value, $offset, $this->lengthMap->getLengthFor($format));
    }

    public function writeInt16BE2($value, $offset = null) {

        $format = 's';
        $this->insert($format, $value, $offset, $this->lengthMap->getLengthFor($format));
    }

    public function writeInt16LE($value, $offset = null) {

        $format = 'v';
        $this->checkForOverSize(0xffff, $value);
        $this->insert($format, $value, $offset, $this->lengthMap->getLengthFor($format));
    }

    public function writeInt32BE($value, $offset = null) {

        $format = 'N';
        $this->checkForOverSize(0xffffffff, $value);
        $this->insert($format, $value, $offset, $this->lengthMap->getLengthFor($format));
    }

    public function writeInt32LE($value, $offset = null) {

        $format = 'V';
        $this->checkForOverSize(0xffffffff, $value);
        $this->insert($format, $value, $offset, $this->lengthMap->getLengthFor($format));
    }

    public function read($offset, $length, $format = 'a') {

        return $this->extract($format . $length, $offset, $length);
    }

    public function readInt8($offset) {

        $format = 'C';

        return $this->extract($format, $offset, $this->lengthMap->getLengthFor($format));
    }

    public function readInt16BE($offset) {

        $format = 'n';

        return $this->extract($format, $offset, $this->lengthMap->getLengthFor($format));
    }

    public function readInt16LE($offset) {

        $format = 'v';

        return $this->extract($format, $offset, $this->lengthMap->getLengthFor($format));
    }

    public function readInt32BE($offset) {

        $format = 'N';

        return $this->extract($format, $offset, $this->lengthMap->getLengthFor($format));
    }

    public function readInt32LE($offset) {

        $format = 'V';

        return $this->extract($format, $offset, $this->lengthMap->getLengthFor($format));
    }

    protected function initializeStructs($length) {

        if ($length === self::DEFAULT_CAPACITY) {
            $this->buffer = [];
        } else {
            $this->buffer = new \SplFixedArray($length);
        }
    }

    protected function insert($format, $value, $offset, $length) {

        if ($offset === null) {
            $offset = $this->currentOffset;
        }
        $bytes = pack($format, $value);
        for ($i = 0; $i < strlen($bytes); $i++) {
            $this->buffer[$offset++] = $bytes[$i];
        }
        $this->currentOffset = $offset;
    }

    protected function extract($format, $offset, $length) {

        $encoded = '';
        for ($i = 0; $i < $length; $i++) {
            $encoded .= $this->buffer[$offset + $i];
        }
        if ($format == 'N' && PHP_INT_SIZE <= 4) {
            list(, $h, $l) = unpack('n*', $encoded);
            $result = ($l + ($h * 0x010000));
        } else {
            if ($format == 'V' && PHP_INT_SIZE <= 4) {
                list(, $h, $l) = unpack('v*', $encoded);
                $result = ($h + ($l * 0x010000));
            } else {
                list(, $result) = unpack($format, $encoded);
            }
        }

        return $result;
    }

    protected function checkForOverSize($excpected_max, $actual) {

        if ($actual > $excpected_max) {
            throw new \InvalidArgumentException(sprintf('%d exceeded limit of %d', $actual, $excpected_max));
        }
    }

}