<?php

namespace BonVet\Services;

use BonVet\Models\Pet;
use BonVet\Models\QrToken;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
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
        
        // Usar DateTime en lugar de now()
        $expiresAt = new \DateTime();
        $expiresAt->modify('+' . $this->qrTtl . ' minutes');
        
        $qrToken = QrToken::create([
            'token' => $token,
            'pet_id' => $pet->id,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'is_active' => true,
            'created_by_ip' => $createdByIp
        ]);

        // Generar QR code - VERSIÓN CORREGIDA
        $accessUrl = $this->baseUrl . '/qr/access/' . $token;
        
        // Opción 1: Versión simplificada (RECOMENDADA)
        $qrCode = QrCode::create($accessUrl)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
            ->setSize(300)
            ->setMargin(10)
            ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin);

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return [
            'token' => $token,
            'access_url' => $accessUrl,
            'qr_code_base64' => base64_encode($result->getString()),
            'qr_code_data_uri' => $result->getDataUri(),
            'expires_at' => $expiresAt->format('c'), // ISO 8601
            'expires_in_minutes' => $this->qrTtl
        ];
    }

    // Versión alternativa con más opciones
    public function generateAdvancedQr(Pet $pet, string $createdByIp = null): array
    {
        // ... mismo código de token ...

        $accessUrl = $this->baseUrl . '/qr/access/' . $token;
        
        // QR con más personalización
        $qrCode = QrCode::create($accessUrl)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->setSize(400)
            ->setMargin(15)
            ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->setForegroundColor(new \Endroid\QrCode\Color\Color(0, 0, 0))
            ->setBackgroundColor(new \Endroid\QrCode\Color\Color(255, 255, 255));

        // Opcional: Agregar label
        $label = Label::create('BonVet - ' . $pet->name)
            ->setTextColor(new \Endroid\QrCode\Color\Color(0, 0, 0));

        $writer = new PngWriter();
        $result = $writer->write($qrCode, null, $label);

        return [
            'token' => $token,
            'access_url' => $accessUrl,
            'qr_code_base64' => base64_encode($result->getString()),
            'qr_code_data_uri' => $result->getDataUri(),
            'expires_at' => $expiresAt->format('c'),
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
        $now = new \DateTime();
        return QrToken::where('expires_at', '<', $now->format('Y-m-d H:i:s'))->delete();
    }
}