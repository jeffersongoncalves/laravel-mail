<?php

use JeffersonGoncalves\LaravelMail\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Generate a self-signed RSA certificate used to emulate the certificate AWS SNS
 * exposes through its SigningCertURL.
 *
 * @return array{0: OpenSSLAsymmetricKey, 1: string} The private key and the PEM-encoded certificate.
 */
function generateSnsCertificate(): array
{
    $config = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    $privateKey = openssl_pkey_new($config);
    $csr = openssl_csr_new(['commonName' => 'sns.amazonaws.com'], $privateKey, $config);
    $certificate = openssl_csr_sign($csr, null, $privateKey, 365, $config);

    openssl_x509_export($certificate, $certificatePem);

    return [$privateKey, $certificatePem];
}

/**
 * Generate an ECDSA (P-256) key pair used to emulate the SendGrid Event Webhook
 * signing key. SendGrid signs with the private key; the public "verification
 * key" is configured on the application side.
 *
 * @return array{0: OpenSSLAsymmetricKey, 1: string} The private key and the PEM-encoded public key.
 */
function generateSendGridKeyPair(): array
{
    $privateKey = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);

    $details = openssl_pkey_get_details($privateKey);

    return [$privateKey, $details['key']];
}
