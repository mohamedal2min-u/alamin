import subprocess, sys

# Try to import paramiko, install if missing
try:
    import paramiko
except ImportError:
    subprocess.check_call([sys.executable, '-m', 'pip', 'install', 'paramiko'])
    import paramiko

HOST = '82.29.181.61'
USER = 'alamin-api'
PASS = 'a550055A!'
PATH = '/home/alamin-api/htdocs/api.alamin.se'

COMMANDS = [
    f'cd {PATH} && git pull origin main 2>&1',
    f'cd {PATH} && php artisan config:cache 2>&1',
    f'cd {PATH} && php artisan route:cache 2>&1',
    f'cd {PATH} && php artisan migrate --force 2>&1',
    f'cd {PATH} && php artisan optimize 2>&1',
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

print(f"Connecting to {HOST} as {USER}...")
client.connect(HOST, username=USER, password=PASS, timeout=15)
print("Connected!\n")

for cmd in COMMANDS:
    print(f">>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode()
    err = stderr.read().decode()
    if out: print(out)
    if err: print("STDERR:", err)
    print()

client.close()
print("Done!")
