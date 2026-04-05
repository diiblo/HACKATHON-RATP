<?php

namespace App\Service;

class QrAccessSimulator
{
    public function getDemoEntries(): array
    {
        return [
            ['code' => 'BUS-72-4589-NATION', 'line' => '72', 'vehicle' => '4589', 'stop' => 'Nation'],
            ['code' => 'BUS-183-7712-CHOISY', 'line' => '183', 'vehicle' => '7712', 'stop' => 'Choisy'],
            ['code' => 'BUS-38-3811-GARE', 'line' => '38', 'vehicle' => '3811', 'stop' => 'Gare du Nord'],
        ];
    }

    public function decode(string $code): ?array
    {
        foreach ($this->getDemoEntries() as $entry) {
            if ($entry['code'] === strtoupper(trim($code))) {
                return $entry;
            }
        }

        return null;
    }
}
