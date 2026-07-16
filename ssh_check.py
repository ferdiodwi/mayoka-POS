import pexpect
import sys

child = pexpect.spawn('ssh -o StrictHostKeyChecking=no mayoka@192.168.1.7', encoding='utf-8')
try:
    i = child.expect(['[Pp]assword:', pexpect.EOF, pexpect.TIMEOUT, r'mayoka@mayoka-server:~\$'], timeout=10)
    if i == 0:
        child.sendline('123')
        child.expect(r'mayoka@mayoka-server:~\$')
    
    # Check current content of app.js
    child.sendline('grep wsHost /var/www/mayoka-app/resources/js/app.js')
    child.expect(r'mayoka@mayoka-server:~\$')
    print("CURRENT WSHOST:")
    print(child.before)
    
    # Replace the line using sed
    sed_cmd = "sed -i \"s/wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname/wsHost: window.location.hostname/g\" /var/www/mayoka-app/resources/js/app.js"
    child.sendline(sed_cmd)
    child.expect(r'mayoka@mayoka-server:~\$')
    
    # Check again
    child.sendline('grep wsHost /var/www/mayoka-app/resources/js/app.js')
    child.expect(r'mayoka@mayoka-server:~\$')
    print("NEW WSHOST:")
    print(child.before)
    
    # Build it
    child.sendline('cd /var/www/mayoka-app && npm run build')
    # npm run build might take a while, so increase timeout
    i = child.expect([r'mayoka@mayoka-server:/var/www/mayoka-app\$', pexpect.TIMEOUT], timeout=60)
    print("NPM BUILD OUTPUT:")
    print(child.before)
    
    child.sendline('exit')
except Exception as e:
    print(f"Exception: {e}")
