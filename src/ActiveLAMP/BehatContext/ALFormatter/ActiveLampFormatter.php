<?php
/**
 * @file
 * Custom output formatter.
 */

use Behat\Behat\Formatter\PrettyFormatter;

namespace ActiveLAMP\BehatContext\ALFormatter;

class ActiveLampFormatter extends PrettyFormatter {

    // Override the output message with this.
    protected $override_text = NULL;

    /**
     * Prints step name.
     *
     * @param StepNode            $step       step node
     * @param DefinitionInterface $definition definition (if found one)
     * @param string              $color      color code
     *
     * @uses colorizeDefinitionArguments()
     */
    protected function printStepName($step, $definition = null, $color)
    {
        $type   = $step->getType();
        $text   = $this->inOutlineSteps ? $step->getCleanText() : $step->getText();
        $indent = $this->stepIndent;

        if (null !== $definition) {
            $text = $this->colorizeDefinitionArguments($text, $definition, $color);
        }

        $text = ($this->override_text) ? $this->override_text : $text;
        $this->write("$indent{+$color}$type $text{-$color}");
        $this->override_text = NULL;
    }

    /**
     * Listens to "step.after" event.
     *
     * @param StepEvent $event
     *
     * @uses printStep()
     */
    public function afterStep($event)
    {
        if ($this->inBackground && $this->isBackgroundPrinted) {
            return;
        }

        if (!$this->inBackground && $this->inOutlineExample) {
            $this->delayedStepEvents[] = $event;
            return;
        }

        $context = $event->getContext();
        if (isset($context->override_text)) {
        	$this->override_text = $context->override_text;
        	$context->override_text = NULL;
        }

        $this->printStep(
            $event->getStep(),
            $event->getResult(),
            $event->getDefinition(),
            $event->getSnippet(),
            $event->getException()
        );
    }

}
