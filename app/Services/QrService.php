<?php

namespace BonVet\Services;

use BonVet\Models\Pet;
use BonVet\Models\QrToken;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Ramsey\Uuid\Uuid;

class QrService
{
    private int $qrTtl;
    private string $baseUrl;

    public function __construct(int $qrTtl, string $baseUrl = 'http://localhost:8000')
    {
        $this->qrTtl = $qrTtl;
        $this->baseUrl = $baseUrl;
    }

    public function generateQrForPet(Pet $pet, string $createdByIp = null): array
    {
        // Desactivar tokens anteriores
        QrToken::where('pet_id', $pet->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Crear nuevo token
        $token = Uuid::uuid4()->toString();
        
        $qrToken = QrToken::create([
            'token' => $token,
            'pet_id' => $pet->id,
            'expires_at' => now()->addMinutes($this->qrTtl),
            'is_active' => true,
            'created_by_ip' => $createdByIp
        ]);

        // Generar QR code
        $accessUrl = $this->baseUrl . '/qr/access/' . $token;
        
        $qrCode = QrCode::create($accessUrl)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
            ->setSize(300)
            ->setMargin(10)
            ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return [
            'token' => $token,
            'access_url' => $accessUrl,
            'qr_code_base64' => base64_encode($result->getString()),
            'qr_code_data_uri' => $result->getDataUri(),
            'expires_at' => $qrToken->expires_at->toISOString(),
            'expires_in_minutes' => $this->qrTtl
        ];
    }

    public function validateToken(string $token): ?QrToken
    {
        $qrToken = QrToken::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (!$qrToken || $qrToken->isExpired()) {
            return null;
        }

        // Marcar como usado
        $qrToken->markAsUsed();

        return $qrToken;
    }

    public function deactivateToken(string $token): bool
    {
        return QrToken::where('token', $token)->update(['is_active' => false]) > 0;
    }

    public function cleanExpiredTokens(): int
    {
        return QrToken::where('expires_at', '<', now())->delete();
    }
}