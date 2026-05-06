<?php

declare(strict_types=1);

namespace App\Service;

use OpenAI;
use OpenAI\Client;
use Psr\Log\LoggerInterface;

class OpenAIService
{
    private ?Client $client;
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        ?string $openaiApiKey = null
    ) {
        $this->logger = $logger;
        
        if (!$openaiApiKey || $openaiApiKey === 'your-openai-api-key-here') {
            // API key no configurada, usar fallback
            $this->client = null;
            return;
        }

        $this->client = OpenAI::client($openaiApiKey);
    }

    public function generateBrandDescription(string $brandName): string
    {
        // Si no hay cliente OpenAI configurado, usar fallback directamente
        if (!$this->client) {
            $this->logger->info('OpenAI client not configured, using fallback description');
            return $this->generateFallbackDescription($brandName);
        }

        try {
            $prompt = sprintf(
                'Genera una descripción profesional y concisa para la marca "%s". 
                La descripción debe ser en español, profesional y adecuada para un sistema de inventario.
                Máximo 200 caracteres. Enfócate en la identidad y valores de la marca.',
                $brandName
            );

            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un experto en marketing y branding que crea descripciones profesionales para marcas.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 100,
                'temperature' => 0.7,
            ]);

            $description = $response->choices[0]->message->content;
            
            // Limpiar y formatear la descripción
            return trim(preg_replace('/\s+/', ' ', $description));
            
        } catch (\Exception $e) {
            $this->logger->error('Error generating brand description', [
                'brand' => $brandName,
                'error' => $e->getMessage()
            ]);
            
            // Fallback a descripción genérica si falla OpenAI
            return $this->generateFallbackDescription($brandName);
        }
    }

    private function generateFallbackDescription(string $brandName): string
    {
        $descriptions = [
            'Marca líder en el mercado con reconocimiento internacional y productos de alta calidad.',
            'Empresa innovadora enfocada en la excelencia y satisfacción del cliente.',
            'Marca con larga trayectoria en la industria, reconocida por su fiabilidad y diseño.',
            'Comprometida con la sostenibilidad y el uso de materiales eco-amigables.',
            'Especializada en productos modernos con tecnología de vanguardia y diseño exclusivo.',
            'Referencia en el sector por su atención al detalle y acabados superiores.',
            'Marca juvenil y dinámica, conectada con las últimas tendencias del mercado.',
            'Calidad y tradición se unen en esta marca con años de experiencia.',
            'Innovadora y disruptiva, cambiando los estándares de la industria.',
            'Premium y exclusiva, dirigida a un segmento exigente y sofisticado.'
        ];

        $hash = crc32($brandName);
        $index = abs($hash) % count($descriptions);

        return $descriptions[$index];
    }
}
