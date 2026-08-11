import pexpect
import sys

def run_ssh_command(host, user, password, command):
    child = pexpect.spawn(f'ssh -o StrictHostKeyChecking=no {user}@{host} "{command}"', encoding='utf-8')
    try:
        child.expect('password:')
        child.sendline(password)
        print(child.read())
    except pexpect.EOF:
        print(child.before)
    except pexpect.TIMEOUT:
        print("Timeout")

if __name__ == '__main__':
    run_ssh_command('192.168.1.22', 'mayoka', '123', sys.argv[1])
