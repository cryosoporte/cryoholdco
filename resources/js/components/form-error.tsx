import { AlertCircleIcon } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";

export function FormError({
    message,
    id,
}: {
    message?: string | null;
    id?: string;
}) {
    if (!message) {
        return null;
    }

    return (
        <Alert variant="destructive" id={id}>
            <AlertCircleIcon />
            <AlertDescription>{message}</AlertDescription>
        </Alert>
    );
}
