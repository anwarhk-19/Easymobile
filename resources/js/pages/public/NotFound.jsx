import { Link } from 'react-router-dom';
import { Button, Empty } from '../../components/ui';

export default function NotFound() {
    return (
        <div className="mx-auto max-w-lg py-12">
            <Empty icon="search" title="Page not found" action={<Button as={Link} to="/">Go to home</Button>}>
                The page you are looking for doesn’t exist or has moved.
            </Empty>
        </div>
    );
}
