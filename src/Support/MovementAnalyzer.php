<?php

namespace CodeDart\SlideCaptcha\Support;

class MovementAnalyzer
{
    public function analyze(array $points)
    {
        $points = array_values(array_filter($points, function ($point) {
            return is_array($point)
                && isset($point['x'], $point['y'], $point['t'])
                && is_numeric($point['x'])
                && is_numeric($point['y'])
                && is_numeric($point['t']);
        }));

        $minPoints = max(1, (int) config('slide-captcha.movement.min_points', 8));

        if (count($points) < $minPoints) {
            return $this->failure('movement_too_short', 'O movimento informado para o CAPTCHA é insuficiente.');
        }

        $first = reset($points);
        $last = end($points);
        $duration = (float) $last['t'] - (float) $first['t'];

        if ($duration < (float) config('slide-captcha.movement.min_duration_ms', 250)) {
            return $this->failure('movement_too_fast', 'O movimento do CAPTCHA foi rápido demais.');
        }

        if ($duration > (float) config('slide-captcha.movement.max_duration_ms', 15000)) {
            return $this->failure('movement_too_slow', 'O movimento do CAPTCHA demorou demais.');
        }

        $sameYMovements = 0;
        $movementCount = 0;

        for ($index = 1; $index < count($points); $index++) {
            $deltaX = abs((float) $points[$index]['x'] - (float) $points[$index - 1]['x']);
            $deltaY = abs((float) $points[$index]['y'] - (float) $points[$index - 1]['y']);

            if ($deltaX < 0.5 && $deltaY < 0.5) {
                continue;
            }

            $movementCount++;

            if ($deltaY < 0.5) {
                $sameYMovements++;
            }
        }

        if ($movementCount === 0) {
            return $this->failure('movement_too_short', 'O movimento informado para o CAPTCHA é insuficiente.');
        }

        $sameYRatio = $sameYMovements / $movementCount;

        if ($sameYRatio > (float) config('slide-captcha.movement.max_same_y_ratio', 0.9)) {
            return $this->failure('movement_too_linear', 'O movimento do CAPTCHA parece automatizado.');
        }

        return [
            'success' => true,
        ];
    }

    protected function failure($reason, $message)
    {
        return [
            'success' => false,
            'reason' => $reason,
            'message' => $message,
        ];
    }
}
