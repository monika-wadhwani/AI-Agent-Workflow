<?php
namespace App\Agents;

use App\Contracts\Tool;

class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(Tool $tool): static{
        $this->tools[$tool->name()] = $tool;

        return $this;
    } 

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

     /**
     * Definitions sent to the model, limited to the tools an agent is allowed to use.
     *
     * @param  list<string>  $allowed
     * @return list<array{name: string, description: string, parameters: array}>
     */

     public function definitionsFor(array $allowed): array
    {
        $definitions = [];

        foreach ($allowed as $name) {
            if ($tool = $this->get($name)) {
                $definitions[] = [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->parameters(),
                ];
            }
        }

        return $definitions;
    }
}   