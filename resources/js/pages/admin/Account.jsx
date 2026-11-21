import { useState } from 'react';
import { Alert, Button, DL, Field, Input, PageHeader, Section } from '../../components/ui';
import { put } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useAction } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { label } from '../../lib/format';

export default function Account() {
    const { user, refresh } = useAuth();
    const toast = useToast();
    const [f, setF] = useState({ current_password: '', password: '', password_confirmation: '' });
    const save = useAction(() => put('/auth/password', f), {
        onSuccess: (r) => { toast(r.message); setF({ current_password: '', password: '', password_confirmation: '' }); refresh(); },
        onError: (e) => toast(e.message, 'error'),
    });
    const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
    return (
        <div className="mx-auto max-w-xl space-y-4">
            <PageHeader title="My account" />
            {user.uses_default_password && <Alert tone="red" title="You are using the default password">It is public (listed in the project documentation). Change it now so nobody else can sign in as you.</Alert>}
            <Section title="Account"><DL items={[['Name', user.name], ['Email', user.email], ['Role', label(user.role)]]} /></Section>
            <Section title="Change password">
                <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); save.run(); }}>
                    <Field label="Current password" error={save.errors.current_password}><Input type="password" autoComplete="current-password" value={f.current_password} onChange={set('current_password')} required /></Field>
                    <Field label="New password" error={save.errors.password} hint="At least 10 characters, with upper- and lower-case letters and a number."><Input type="password" autoComplete="new-password" value={f.password} onChange={set('password')} required /></Field>
                    <Field label="Repeat new password"><Input type="password" autoComplete="new-password" value={f.password_confirmation} onChange={set('password_confirmation')} required /></Field>
                    <Button busy={save.busy}>Change password</Button>
                </form>
            </Section>
        </div>
    );
}
