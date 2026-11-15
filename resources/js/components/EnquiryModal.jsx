import { useEffect, useState } from 'react';
import { Button, Field, Modal, Select, Textarea, WhatsAppButton } from './ui';
import { get, post } from '../lib/api';
import { useAction } from '../lib/hooks';
import { useToast } from '../lib/toast';

export default function EnquiryModal({ open, onClose, product, shop }) {
    const toast = useToast();
    const [message, setMessage] = useState(product ? `Is "${product.name}" available? Please confirm the final price.` : '');
    const [modelId, setModelId] = useState('');
    const [models, setModels] = useState([]);
    const [done, setDone] = useState(null);
    useEffect(() => { if (product?.compatible_models?.length) setModels(product.compatible_models); else if (open && product) get('/phone-models').then(setModels).catch(() => {}); }, [open, product]);
    const send = useAction(() => post('/enquiries', { product_id: product?.id, shop_id: shop.id, message, phone_model_id: modelId || undefined }),
        { onSuccess: (r) => { setDone(r); toast('Enquiry sent to ' + shop.name); }, onError: (e) => toast(e.message, 'error') });

    return (
        <Modal open={open} onClose={() => { setDone(null); onClose(); }} title={done ? 'Enquiry sent' : `Ask ${shop.name}`}
            footer={done ? <Button onClick={() => { setDone(null); onClose(); }}>Done</Button> : <><Button variant="secondary" onClick={onClose}>Cancel</Button><Button busy={send.busy} onClick={() => send.run()}>Send enquiry</Button></>}>
            {done ? (
                <div className="space-y-4 text-sm text-slate-600">
                    <p>Your enquiry <b>{done.enquiry.reference}</b> is in the shop’s inbox. You’ll be notified when they reply. For a faster answer, message them on WhatsApp:</p>
                    <WhatsAppButton href={done.whatsapp_link} className="w-full" />
                </div>
            ) : (
                <div className="space-y-4">
                    {product && models.length > 0 && <Field label="For which phone model?"><Select value={modelId} onChange={(e) => setModelId(e.target.value)} placeholder="Not specific" options={models.map((m) => ({ value: m.id, label: `${m.brand?.name ?? ''} ${m.name}`.trim() }))} /></Field>}
                    <Field label="Your question" error={send.errors.message}><Textarea rows={4} value={message} onChange={(e) => setMessage(e.target.value)} /></Field>
                </div>
            )}
        </Modal>
    );
}
