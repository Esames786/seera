#!/usr/bin/env bash
# Rehearse the "rebuild as InnoDB" recovery on a disposable local MySQL database.
# 1. Create the database with MyISAM as the server default and migrate the pre-November schema (mimics production history).
# 2. Seed production-style setup data (ProductionBootstrapSeeder).
# 3. Dump only the setup tables (data only), migrate:fresh with InnoDB default on the corrective candidate, reload, verify.
set -uo pipefail
M=d:/laragon2/bin/mysql/mysql-8.0.30-winx64/bin
PHP=d:/laragon2/bin/php/php-8.3.16-Win32-vs16-x64/php.exe
DB=seera_rebuild_test
S="C:/Users/Dell/AppData/Local/Temp/claude/d--laragon2-www-seera/137b5bc7-77f9-4c94-b46e-54bc7c2cfa0e/scratchpad"
cd /d/laragon2/www/seera
export APP_ENV=local DB_DATABASE=$DB SEERA_ADMIN_NAME="Admin User" SEERA_ADMIN_EMAIL=admin@seera-test.local SEERA_ADMIN_USERNAME=admin SEERA_ADMIN_PASSWORD="RehearsalOnly-2026-11-10" SEERA_ORG_EMAIL_DOMAIN=seera-test.local
SETUP="users user_roles roles permissions role_permissions role_reporting_parents branches departments designations company_profiles chart_of_accounts cost_centers automatic_posting_rules vat_periods zatca_egs_units lookup_values payment_terms units item_categories expense_categories leave_types shifts approval_workflows approval_workflow_steps project_classifications document_sequences"
step() { printf '\n== %s\n' "$1"; }

step "1. disposable database with the pre-November schema"
"$M/mysql.exe" -uroot -e "drop database if exists $DB; create database $DB character set utf8mb4 collate utf8mb4_unicode_ci;"
# Pre-November schema only: run every migration except the two November files.
mkdir -p "$S/mig-old" && rm -f "$S/mig-old"/*.php && for f in database/migrations/*.php; do case "$f" in *2026_11_10_*) ;; *) cp "$f" "$S/mig-old/";; esac; done
"$PHP" artisan migrate:fresh --force --realpath --path="$S/mig-old" 2>&1 | tail -2
"$M/mysql.exe" -uroot -N -e "select engine, count(*) from information_schema.tables where table_schema='$DB' group by engine"
step "2. production-style setup data"
"$PHP" artisan db:seed --class=ProductionBootstrapSeeder --force 2>&1 | grep -E "complete|Remove|ERROR|Exception" | head -3
"$M/mysql.exe" -uroot -e "set global default_storage_engine=InnoDB;"
before="$S/rehearsal-before.txt"; after="$S/rehearsal-after.txt"
for t in $SETUP; do printf '%s %s\n' "$t" "$("$M/mysql.exe" -uroot -N -e "select count(*) from $DB.$t")"; done > "$before"
printf 'setup rows before: %s\n' "$(awk '{s+=$2} END {print s}' "$before")"
"$M/mysql.exe" -uroot -N -e "select count(*) from information_schema.referential_constraints where constraint_schema='$DB'" | sed 's/^/foreign keys before: /'

step "3. rebuild: data-only dump of the setup tables"
# shellcheck disable=SC2086
"$M/mysqldump.exe" -uroot --no-create-info --skip-triggers --complete-insert --single-transaction $DB $SETUP > "$S/rehearsal-setup-data.sql" && printf 'dump bytes: %s\n' "$(wc -c < "$S/rehearsal-setup-data.sql")"
step "4. migrate:fresh on the corrective candidate (InnoDB default, both November migrations included)"
"$PHP" artisan migrate:fresh --force 2>&1 | grep -E "2026_11_10|ERROR|Exception|STOP" | head -4
step "5. reload the setup rows with foreign-key checks off"
{ echo "SET FOREIGN_KEY_CHECKS=0; SET UNIQUE_CHECKS=0;"; for t in $SETUP; do echo "TRUNCATE TABLE \`$t\`;"; done; cat "$S/rehearsal-setup-data.sql"; echo "SET FOREIGN_KEY_CHECKS=1; SET UNIQUE_CHECKS=1;"; } | "$M/mysql.exe" -uroot $DB && echo "reload ok"
step "6. verify"
for t in $SETUP; do printf '%s %s\n' "$t" "$("$M/mysql.exe" -uroot -N -e "select count(*) from $DB.$t")"; done > "$after"
if diff "$before" "$after" >/dev/null; then echo "setup row counts identical: YES ($(awk '{s+=$2} END {print s}' "$after") rows)"; else echo "setup row counts DIFFER:"; diff "$before" "$after"; fi
"$M/mysql.exe" -uroot -N -e "select engine, count(*) from information_schema.tables where table_schema='$DB' group by engine" | sed 's/^/engines after: /'
"$M/mysql.exe" -uroot -N -e "select count(*) from information_schema.referential_constraints where constraint_schema='$DB'" | sed 's/^/foreign keys after: /'
"$PHP" artisan migrate:status 2>&1 | grep -c Pending | sed 's/^/pending migrations: /'
"$PHP" scripts/diagnose-feedback-storage.php 2>&1 | "$PHP" -r '$j=json_decode(stream_get_contents(STDIN),true); echo "diagnose: read_only=",(int)($j["read_only"]??0),"; new tables: "; foreach($j["new_tables"]??[] as $n=>$v) echo $n,"=",(int)$v["exists"],"/fk",count($v["foreign_keys"]??[])," "; echo PHP_EOL;'
# orphan scan of the reloaded data (the setup rows reference each other only)
"$PHP" -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $p=0; foreach(DB::select("select TABLE_NAME c, COLUMN_NAME k, REFERENCED_TABLE_NAME p, REFERENCED_COLUMN_NAME pk from information_schema.KEY_COLUMN_USAGE where TABLE_SCHEMA=database() and REFERENCED_TABLE_NAME is not null") as $f){ $n=DB::table($f->c." as c")->leftJoin($f->p." as p","c.".$f->k,"=","p.".$f->pk)->whereNotNull("c.".$f->k)->whereNull("p.".$f->pk)->count(); if($n){ $p++; echo "ORPHANS ",$f->c,".",$f->k," -> ",$f->p,": ",$n,PHP_EOL; } } echo "dangling references: ",$p,PHP_EOL;'
"$PHP" -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $u=App\Models\User::whereHas("roles", fn($q)=>$q->where("code","SUPER_ADMIN"))->first(); echo "super admin present: ",(int)(bool)$u,"; roles: ",App\Models\Role::count(),"; permissions: ",App\Models\Permission::count(),"; admin has Users view: ",(int)($u?->hasPermission("Users","view")),PHP_EOL;'
step "7. cleanup"
"$M/mysql.exe" -uroot -e "drop database if exists $DB; set global default_storage_engine=InnoDB;" && echo "disposable database dropped, server default InnoDB"
