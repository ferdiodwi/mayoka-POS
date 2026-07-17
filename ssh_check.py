import pexpect
import sys

child = pexpect.spawn('ssh -o StrictHostKeyChecking=no mayoka@192.168.1.7', encoding='utf-8')
try:
    i = child.expect(['[Pp]assword:', pexpect.EOF, pexpect.TIMEOUT, r'mayoka@mayoka-server:~\$'], timeout=10)
    if i == 0:
        child.sendline('123')
        child.expect(r'mayoka@mayoka-server:~\$')
    
    # Run sed to replace the reverb options in config/broadcasting.php
    sed_script = r"""
sed -i -e "s/'host' => env('REVERB_HOST')/'host' => env('REVERB_SERVER_HOST', '127.0.0.1')/g" \
       -e "s/'port' => env('REVERB_PORT', 443)/'port' => env('REVERB_SERVER_PORT', 8080)/g" \
       -e "s/'scheme' => env('REVERB_SCHEME', 'https')/'scheme' => 'http'/g" \
       -e "s/'useTLS' => env('REVERB_SCHEME', 'https') === 'https'/'useTLS' => false/g" \
       /var/www/mayoka-app/config/broadcasting.php
"""
    child.sendline(sed_script)
    child.expect(r'mayoka@mayoka-server:~\$')
    print("SED OUTPUT:")
    print(child.before)
    
    # Clear config cache
    child.sendline('cd /var/www/mayoka-app && php artisan config:clear')
    child.expect(r'mayoka@mayoka-server:/var/www/mayoka-app\$')
    print("ARTISAN OUTPUT:")
    print(child.before)
    
    child.sendline('exit')
except Exception as e:
    print(f"Exception: {e}")
