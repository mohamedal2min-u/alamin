import subprocess, sys

try:
    import paramiko
except ImportError:
    subprocess.check_call([sys.executable, '-m', 'pip', 'install', 'paramiko'])
    import paramiko

HOST = '82.29.181.61'
USER = 'alamin-api'
PASS = 'a550055A!'
PATH = '/home/alamin-api/htdocs/api.alamin.se'

def run_remote_cmd(client, cmd):
    print(f"\n--- Executing: {cmd} ---")
    try:
        stdin, stdout, stderr = client.exec_command(cmd)
        out = stdout.read().decode().strip()
        err = stderr.read().decode().strip()
        if out: print(out)
        if err: print(f"ERROR/STDERR: {err}")
        return out, err
    except Exception as e:
        print(f"Command execution failed: {e}")
        return "", str(e)

try:
    print(f"Connecting to {HOST}...")
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASS, timeout=15)
    print("Connected successfully!")
    
    # 1. Check Disk Space
    run_remote_cmd(client, "df -h")
    
    # 2. Check Memory Usage
    run_remote_cmd(client, "free -m")
    
    # 3. Check Processes
    run_remote_cmd(client, "ps aux | grep -E 'php|nginx' | grep -v grep")
    
    # 4. Check Laravel Logs
    run_remote_cmd(client, f"tail -n 20 {PATH}/storage/logs/laravel.log")
    
    # 5. Check if it's a service issue (Systemd)
    run_remote_cmd(client, "systemctl status php8.2-fpm --no-pager || systemctl status php-fpm --no-pager")

    client.close()
    print("\nDiagnostic complete.")
except Exception as e:
    print(f"Failed to connect: {e}")
