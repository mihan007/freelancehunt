<?php

class Config
{
    public const CSV_PATH = 'CSV_example.csv';
    public const CSV_DELIMITER = ';';
    public const GALLERY_COLUMN_DELIMITER = ',';
    public const COLUMN_NAMES = [
        'photo' => 'Photo',
        'sku' => 'SKU',
        'gallery' => 'Gallery'
    ];
}

class PrepareGalleryColumn
{
    /** @var Config */
    private $config;

    private $result;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): \PrepareGalleryColumn
    {
        $incomingCsvFilePath = $this->config::CSV_PATH;
        $handle = fopen($incomingCsvFilePath, 'rb');
        if ($handle === false) {
            throw new Exception("Could not open {$incomingCsvFilePath} file. Please check config");
        }
        $headers = fgetcsv($handle, 0, $this->config::CSV_DELIMITER);
        $photoColumnIndex = null;
        $skuColumnIndex = null;
        foreach ($headers as $ind => $headerName) {
            if ($this->normalizeString($headerName) == $this->config::COLUMN_NAMES['photo']) {
                $photoColumnIndex = $ind;
                continue;
            }
            if ($this->normalizeString($headerName) == $this->config::COLUMN_NAMES['sku']) {
                $skuColumnIndex = $ind;
                continue;
            }
        }
        if (($photoColumnIndex === null) || ($skuColumnIndex === null)) {
            throw new Exception("Could not find columns for photo and sku. Please check config");
        }
        $this->result = [
            0 => [
                $this->config::COLUMN_NAMES['photo'],
                $this->config::COLUMN_NAMES['sku'],
                $this->config::COLUMN_NAMES['gallery'],
            ]
        ];
        $photos = [];
        $original = [];
        while (($data = fgetcsv($handle, 0, $this->config::CSV_DELIMITER)) !== false) {
            $original[] = $data;
            $normalizedSku = $this->normalizeString($data[$skuColumnIndex] ?? false);
            if (!$normalizedSku) {
                continue;
            }
            $index = $normalizedSku;
            if (!isset($photos[$index])) {
                $photos[$index] = [];
            }
            $normalizedPhoto = $this->normalizeString($data[$photoColumnIndex] ?? false);
            if (!$normalizedPhoto) {
                continue;
            }
            $photos[$index][] = $normalizedPhoto;
        }
        fclose($handle);
        foreach ($original as $ind => $row) {
            $normalizedSku = $this->normalizeString($row[$skuColumnIndex]);
            $this->result[] = [
                $row[$photoColumnIndex],
                $row[$skuColumnIndex],
                implode($this->config::GALLERY_COLUMN_DELIMITER, $photos[$normalizedSku])
            ];
            $photos[$normalizedSku] = [];
        }

        return $this;
    }

    public function storeTo(string $output): void
    {
        $outstream = fopen($output, 'wb+');
        $this->toCSV($this->result, $outstream);
        fclose($outstream);
    }

    private function toCSV($data, $outstream): void
    {
        if (!is_array($data)) {
            return;
        }
        // get header from keys
        fputcsv($outstream, array_keys($data[0]), $this->config::CSV_DELIMITER);
        //
        foreach ($data as $row) {
            fputcsv($outstream, $row, $this->config::CSV_DELIMITER);
        }
    }

    /**
     * @param $string
     * @return string
     */
    private function normalizeString($string): string
    {
        return $this->mb_trim($this->remove_utf8_bom($string));
    }

    function mb_trim($string, $trim_chars = '\s')
    {
        return preg_replace('/^[' . $trim_chars . ']*(?U)(.*)[' . $trim_chars . ']*$/u', '\\1', $string);
    }

    function remove_utf8_bom($text)
    {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }
}

$output = 'CSV_example_result.csv';
(new PrepareGalleryColumn(new Config()))
    ->handle()
    ->storeTo($output);
