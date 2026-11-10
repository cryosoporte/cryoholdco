import type { ComponentProps } from "react";
import { Dialog as DialogPrimitive } from "@base-ui/react/dialog";
import { XIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";

const Dialog = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;
const DialogClose = DialogPrimitive.Close;

function DialogContent({
    className,
    children,
    ...props
}: DialogPrimitive.Popup.Props) {
    return (
        <DialogPrimitive.Portal>
            <DialogPrimitive.Backdrop className="fixed inset-0 z-50 bg-black/40 transition-opacity data-ending-style:opacity-0 data-starting-style:opacity-0" />
            <DialogPrimitive.Popup
                data-slot="dialog-content"
                className={cn(
                    "fixed left-1/2 top-1/2 z-50 grid max-h-[90dvh] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 overflow-y-auto rounded-xl border bg-background p-6 text-foreground shadow-lg outline-none",
                    className,
                )}
                {...props}
            >
                {children}
                <DialogPrimitive.Close
                    render={
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            className="absolute right-3 top-3"
                        />
                    }
                >
                    <XIcon />
                    <span className="sr-only">Cerrar</span>
                </DialogPrimitive.Close>
            </DialogPrimitive.Popup>
        </DialogPrimitive.Portal>
    );
}

function DialogHeader({ className, ...props }: ComponentProps<"div">) {
    return (
        <div
            data-slot="dialog-header"
            className={cn("flex flex-col gap-2 pr-8", className)}
            {...props}
        />
    );
}

function DialogTitle({ className, ...props }: DialogPrimitive.Title.Props) {
    return (
        <DialogPrimitive.Title
            data-slot="dialog-title"
            className={cn("text-lg font-semibold", className)}
            {...props}
        />
    );
}

function DialogDescription({
    className,
    ...props
}: DialogPrimitive.Description.Props) {
    return (
        <DialogPrimitive.Description
            data-slot="dialog-description"
            className={cn("text-sm text-muted-foreground", className)}
            {...props}
        />
    );
}

export {
    Dialog,
    DialogTrigger,
    DialogClose,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
};
