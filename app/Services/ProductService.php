<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductService
{
    public function create(array $data, ?UploadedFile $photo, User $user): Product
    {
        $stock = (int) ($data['initial_stock'] ?? 0);
        unset($data['initial_stock'], $data['photo'], $data['remove_photo']);
        $data = $this->normalizePackaging($data);

        $path = $photo?->store('products', 'public');

        try {
            return DB::transaction(function () use ($data, $stock, $path, $user) {
                $product = $user->store->products()->make($data);
                $product->stock = $stock; // stock tidak mass-assignable
                $product->photo = $path;
                $product->save();

                if ($stock > 0) {
                    $user->store->stockMovements()->create([
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'type' => StockMovementType::InitialStock,
                        'quantity_change' => $stock,
                        'stock_before' => 0,
                        'stock_after' => $stock,
                        'notes' => 'Stok awal saat barang dibuat',
                    ]);
                }

                return $product;
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }
    }

    public function update(Product $product, array $data, ?UploadedFile $photo, bool $removePhoto): Product
    {
        unset($data['photo'], $data['remove_photo']);
        $data = $this->normalizePackaging($data);

        $oldPhoto = $product->photo;
        $newPhoto = $photo?->store('products', 'public');

        $product->fill($data);

        if ($newPhoto) {
            $product->photo = $newPhoto;
        } elseif ($removePhoto) {
            $product->photo = null;
        }

        $product->save();

        if ($oldPhoto && ($newPhoto || $removePhoto)) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return $product;
    }

    public function generatePrefix(string $name): string
    {
        $name = trim((string) preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
        if (empty($name)) {
            return 'BRG';
        }

        $words = array_values(array_filter(explode(' ', $name)));
        $firstWord = strtoupper($words[0] ?? '');

        $dictionary = [
            'BERAS' => 'BRS',
            'TEPUNG' => 'TPG',
            'GULA' => 'GLA',
            'MINYAK' => 'MNY',
            'MINYAKITA' => 'MNY',
            'BAWANG' => 'BWG',
            'BISKUIT' => 'BSK',
            'PEWANGI' => 'PWG',
            'GARAM' => 'GRM',
            'TELUR' => 'TLR',
            'AIR' => 'AIR',
            'KOPI' => 'KOP',
            'TEH' => 'TEH',
            'MIE' => 'MIE',
            'MI' => 'MIE',
            'SABUN' => 'SBN',
            'DETERJEN' => 'DTR',
            'DETERGENT' => 'DTR',
            'SUSU' => 'SSU',
            'ROTI' => 'RTI',
            'ROKOK' => 'RKK',
            'SNACK' => 'SNK',
            'BUMBU' => 'BMB',
            'KECAP' => 'KCP',
            'SAOS' => 'SAO',
            'SAUS' => 'SAU',
            'SAMBAL' => 'SBL',
            'PASTA' => 'PST',
            'SHAMPOO' => 'SMP',
            'SAMPO' => 'SMP',
            'SIKAT' => 'SKT',
            'ODOL' => 'ODL',
            'TISU' => 'TSU',
            'TISSUE' => 'TSU',
            'PAMPERS' => 'PMP',
            'POPOK' => 'PPK',
            'OBAT' => 'OBT',
            'VITAMIN' => 'VIT',
            'MINUMAN' => 'MNM',
            'MAKANAN' => 'MKN',
            'SIRUP' => 'SRP',
            'COKLAT' => 'CKL',
            'CHOCOLATE' => 'CKL',
            'KEJU' => 'KJU',
            'MENTEGA' => 'MTG',
            'MARGARIN' => 'MRG',
            'SARDEN' => 'SRD',
            'IKAN' => 'IKN',
            'AYAM' => 'AYM',
            'DAGING' => 'DGG',
            'UDANG' => 'UDG',
            'CUMI' => 'CMI',
            'BEBEK' => 'BBK',
            'LELE' => 'LLE',
            'CABAI' => 'CBI',
            'CABE' => 'CBI',
            'TOMAT' => 'TMT',
            'SAYUR' => 'SYR',
            'BUAH' => 'BUH',
            'SOSIS' => 'SSS',
            'NUGGET' => 'NGT',
            'BAKSO' => 'BKS',
            'GALON' => 'GLN',
            'GAS' => 'GAS',
            'LPG' => 'LPG',
        ];

        if (isset($dictionary[$firstWord])) {
            return $dictionary[$firstWord];
        }

        // Jika kata pertama diawali konsonan dan memiliki minimal 3 konsonan:
        $consonants = (string) preg_replace('/[AEIOU0-9]/i', '', $firstWord);
        $firstIsVowel = in_array($firstWord[0] ?? '', ['A', 'E', 'I', 'O', 'U']);

        if (! $firstIsVowel && strlen($consonants) >= 3) {
            return substr($consonants, 0, 3);
        }

        // Jika kata pertama >= 3 karakter alfanumerik:
        $cleanFirst = (string) preg_replace('/[^A-Z0-9]/', '', $firstWord);
        if (strlen($cleanFirst) >= 3) {
            return substr($cleanFirst, 0, 3);
        }

        // Jika kata pertama pendek (<3), gabung seluruh kata:
        $allClean = (string) preg_replace('/[^A-Z0-9]/', '', strtoupper(implode('', $words)));
        if (strlen($allClean) >= 3) {
            return substr($allClean, 0, 3);
        }

        return str_pad($allClean ?: 'BRG', 3, 'X');
    }

    public function generateCode(string $name, int $storeId, ?int $ignoreProductId = null): string
    {
        $prefix = $this->generatePrefix($name);

        $existingCodes = Product::withTrashed()
            ->where('store_id', $storeId)
            ->when($ignoreProductId, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->where('code', 'like', "{$prefix}-%")
            ->pluck('code');

        $maxNumber = 0;
        foreach ($existingCodes as $code) {
            if (preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $nextNumber = $maxNumber + 1;

        return sprintf('%s-%03d', $prefix, $nextNumber);
    }

    private function normalizePackaging(array $data): array
    {
        if (empty($data['pack_unit'])) {
            $data['pack_unit'] = null;
            $data['units_per_pack'] = 1;
        }

        return $data;
    }
}
