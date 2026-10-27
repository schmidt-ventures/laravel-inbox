<?php

use Illuminate\Support\Facades\Route;
use SchmidtVentures\Inbox\Http\InboxController;
use SchmidtVentures\Inbox\Http\Middleware\EnsureInboxEnabled;
use SchmidtVentures\Inbox\Http\Middleware\VerifyInboxToken;
use SchmidtVentures\Inbox\Inbox;
use SchmidtVentures\Inbox\MessageStore;

Route::middleware([EnsureInboxEnabled::class, VerifyInboxToken::class])
    ->prefix(app(Inbox::class)->routePrefix())
    ->name('inbox.')
    ->group(function () {
        Route::get('/', [InboxController::class, 'index'])->name('index');
        Route::delete('/', [InboxController::class, 'destroyAll'])->name('destroy-all');
        Route::get('/{message}', [InboxController::class, 'show'])
            ->where(['message' => MessageStore::ID])
            ->name('show');
        Route::delete('/{message}', [InboxController::class, 'destroy'])
            ->where(['message' => MessageStore::ID])
            ->name('destroy');
        Route::get('/{message}/html', [InboxController::class, 'html'])
            ->where(['message' => MessageStore::ID])
            ->name('html');
        Route::get('/{message}/attachments/{index}', [InboxController::class, 'attachment'])
            ->where(['message' => MessageStore::ID])
            ->whereNumber('index')
            ->name('attachment');
    });
