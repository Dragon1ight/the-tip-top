<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

trait PlayResponse
{
    private function playResponse(array $r): Response
    {
        if ($r['ok']) {
            return $this->redirectToRoute('gain_show', ['id' => $r['gain_id'], 'nouveau' => 1]);
        }
        $this->addFlash('error', $r['message']);

        return $this->redirectToRoute('deja' === $r['error'] ? 'my_gains' : 'home');
    }
}
