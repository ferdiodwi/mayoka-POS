import pexpect

def run_remote():
    child = pexpect.spawn('ssh mayoka@192.168.1.100', encoding='utf-8', timeout=15)
    idx = child.expect(['assword:', pexpect.EOF, pexpect.TIMEOUT])
    if idx != 0:
        print("Could not connect to server yet.")
        return
    child.sendline('123')
    child.expect(r'mayoka@mayoka-server:~\$ ')
    
    # Switch to root
    child.sendline("sudo su -")
    child.expect(['assword', r'root@mayoka-server:~# '])
    child.sendline('123')
    child.expect(r'root@mayoka-server:~# ')
    
    # Rewrite the systemd service the correct way
    unit_content = """[Unit]
Description=Backup Database Mayoka Sebelum Server Mati
Requires=mysql.service
After=mysql.service

[Service]
Type=oneshot
RemainAfterExit=true
ExecStart=/bin/true
ExecStop=/usr/local/bin/backup_mayoka.sh
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
"""
    child.sendline("cat << 'EOF' > /etc/systemd/system/backup-sebelum-mati.service\n" + unit_content + "EOF")
    child.expect(r'root@mayoka-server:~# ')
    
    # Reload and re-enable
    child.sendline("systemctl daemon-reload")
    child.expect(r'root@mayoka-server:~# ')
    child.sendline("systemctl disable backup-sebelum-mati.service")
    child.expect(r'root@mayoka-server:~# ')
    child.sendline("systemctl enable backup-sebelum-mati.service")
    child.expect(r'root@mayoka-server:~# ')
    
    # Start it now so it will stop at shutdown
    child.sendline("systemctl start backup-sebelum-mati.service")
    child.expect(r'root@mayoka-server:~# ')
    
    # Check the logs of the previous failed shutdown attempt to confirm
    child.sendline("journalctl -u backup-sebelum-mati.service -b -1")
    child.expect(r'root@mayoka-server:~# ')
    print("--- Logs from previous shutdown ---")
    print(child.before)

    child.sendline('exit')
    child.sendline('exit')

run_remote()
