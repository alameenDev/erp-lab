using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

// No private key material is returned. TPM errors never silently downgrade.
try
{
    if (args.Length != 2 || args[0] is not ("tpm" or "software")) throw new Exception();
    var challenge = Convert.FromBase64String(args[1]);
    if (challenge.Length != 32) throw new Exception();
    var binding = args[0];
    var provider = new CngProvider(binding == "tpm" ? "Microsoft Platform Crypto Provider" : "Microsoft Software Key Storage Provider");
    var name = "DigitalLab.Desktop.V1." + binding;
    using var key = CngKey.Exists(name, provider) ? CngKey.Open(name, provider) : CngKey.Create(CngAlgorithm.Rsa, name,
        new CngKeyCreationParameters {
            Provider = provider, ExportPolicy = CngExportPolicies.None, KeyUsage = CngKeyUsages.Signing,
            Parameters = { new CngProperty("Length", BitConverter.GetBytes(2048), CngPropertyOptions.None) }
        });
    using var rsa = new RSACng(key);
    var spki = rsa.ExportSubjectPublicKeyInfo();
    var deviceId = Convert.ToHexString(SHA256.HashData(spki)).ToLowerInvariant();
    var signature = rsa.SignData(challenge, HashAlgorithmName.SHA256, RSASignaturePadding.Pkcs1);
    Console.Write(JsonSerializer.Serialize(new { binding, deviceId, spki = Convert.ToBase64String(spki),
        challenge = Convert.ToBase64String(challenge), proof = Convert.ToBase64String(signature) }));
    return 0;
}
catch
{
    Console.Error.Write("Device key unavailable. Check TPM, Windows account and device provisioning; no fallback was applied.");
    return 2;
}
